<?php

class PDODB {

    private PDO $pdo;
    private string $dbAlias = '';
    private string $realDBName = '';
    private int $rowCount = 0;
    private static array $instances = [];
    private ?PDOStatement $lastStatement = null;
    private string $lastPublicId = '';

    /* ---------------------------
      Transaction & Lock Tracking
      ---------------------------- */
    private int $transactionLevel = 0;
    private int $lastTransactionStart = 0;
    private bool $tablesLocked = false;
    private array $lockedTables = [];

    /* ---------------------------
      Statement cache
      ---------------------------- */
    private array $statementCache = [];
    private int $statementCacheLimit = 100;

    /* ---------------------------
      Constructor / Factory
      ---------------------------- */

    private function __construct(string $name, array $config) {
        $cfg = $config[$name];

        $dsn = "mysql:host={$cfg['host']};dbname={$cfg['dbname']};charset=utf8mb4";
        $opt = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        $this->pdo = new PDO($dsn, $cfg['user'], $cfg['password'], $opt);
        $this->pdo->exec("SET SESSION sql_mode = 'NO_ZERO_DATE,NO_ZERO_IN_DATE'");

        $this->dbAlias = $name;
        $this->realDBName = $cfg['dbname'];
    }

    public static function getInstance(string $name, array $cfg): self {
        if (!isset(self::$instances[$name])) {
            self::$instances[$name] = new self($name, $cfg);
        }
        return self::$instances[$name];
    }

    public function getPDO(): PDO {
        return $this->pdo;
    }

    /* ---------------------------
      Query helpers
      ---------------------------- */

    public function query(string|PDOStatement $sql, array $params = []): array {
        $stmt = $this->prepareCached($sql);
        $stmt->execute($params);

        $this->rowCount = $stmt->rowCount();
        $result = $stmt->fetchAll();
        $stmt->closeCursor();

        return $result ?: [];
    }

    public function queryFetchOne(string $sql, array $params = []): mixed {
        if (!preg_match('/\blimit\b/i', $sql)) {
            $sql .= ' LIMIT 1';
        }
        $r = $this->query($sql, $params);
        return $r[0] ?? null;
    }

    public function queryMode(string $sql, string $mode, array $params = []): array|int {
        $mode = strtolower($mode);
        $stmt = $this->prepareCached($sql);
        $stmt->execute($params);

        if ($mode === 'exec') {
            $this->rowCount = $stmt->rowCount();
            return $this->rowCount;
        }

        $map = [
            'object' => PDO::FETCH_OBJ,
            'assoc' => PDO::FETCH_ASSOC,
            'both' => PDO::FETCH_BOTH,
            'num' => PDO::FETCH_NUM,
            'column' => PDO::FETCH_COLUMN,
        ];

        if (!isset($map[$mode])) {
            throw new InvalidArgumentException("Unknown fetch mode: $mode");
        }

        $result = $stmt->fetchAll($map[$mode]);
        $stmt->closeCursor();

        return $result;
    }

    /* ---------------------------
      Statement caching
      ---------------------------- */

    private function normalizeSql(string $sql): string {
        return trim(str_replace(["\r\n", "\r"], "\n", $sql));
    }

    private function prepareCached(string|PDOStatement $sql): PDOStatement {
        if ($sql instanceof PDOStatement) {
            return $this->lastStatement = $sql;
        }

        $normalized = $this->normalizeSql($sql);
        $hash = sha1($normalized);

        if (!isset($this->statementCache[$hash])) {
            $stmt = $this->pdo->prepare($normalized);

            if (count($this->statementCache) >= $this->statementCacheLimit) {
                array_shift($this->statementCache);
            }

            $this->statementCache[$hash] = $stmt;
        }

        return $this->lastStatement = $this->statementCache[$hash];
    }

    /* ---------------------------
      insertPublicId
      ---------------------------- */

    public function insertPublicId(string $table, array|object|null $data = null): int {
        $data = is_object($data) ? (array) $data : ($data ?? []);

        if (empty($data['public_id'])) {
            $data['public_id'] = bin2hex(random_bytes(16));
        }

        $cols = array_keys($data);
        $ph = array_map(fn($c) => ':' . $c, $cols);

        $sql = sprintf(
                "INSERT INTO `%s` (%s) VALUES (%s)",
                $table,
                implode(',', array_map(fn($c) => "`$c`", $cols)),
                implode(',', $ph)
        );

        $updates = [];
        foreach ($cols as $c) {
            if ($c !== 'public_id') {
                $updates[] = "`$c` = VALUES(`$c`)";
            }
        }

        if ($updates) {
            $sql .= ' ON DUPLICATE KEY UPDATE ' . implode(',', $updates);
        }

        $attempts = 0;
        while (true) {
            try {
                $this->query($sql, $data);
                $this->lastPublicId = $data['public_id'];
                return (int) $this->pdo->lastInsertId();
            } catch (PDOException $e) {
                if ($e->getCode() === '23000' && ++$attempts < 5) {
                    $data['public_id'] = bin2hex(random_bytes(16));
                    continue;
                }
                throw $e;
            }
        }
    }

    public function lastPublicId(): string {
        return $this->lastPublicId;
    }

    /* ---------------------------
      Transactions (nested-safe)
      ---------------------------- */

    public function begin(): void {
        if ($this->transactionLevel === 0) {
            $this->pdo->beginTransaction();
            $this->lastTransactionStart = time();
        }
        $this->transactionLevel++;
    }

    public function commit(): void {
        if ($this->transactionLevel === 0) {
            return;
        }
        $this->transactionLevel--;

        if ($this->transactionLevel === 0 && $this->pdo->inTransaction()) {
            $this->pdo->commit();
            $this->lastTransactionStart = 0;
        }
    }

    public function rollback(): void {
        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
        $this->transactionLevel = 0;
        $this->lastTransactionStart = 0;
    }

    public function isInTransaction(): bool {
        return $this->transactionLevel > 0;
    }

    /* ---------------------------
      Table locking
      ---------------------------- */

    public function lockTables(string $sql): void {
        $this->pdo->exec($sql);
        $this->tablesLocked = true;

        if (preg_match_all('/([a-z0-9_]+)\s+(read|write)/i', $sql, $m)) {
            $this->lockedTables = array_map('strtolower', $m[1]);
        }
    }

    public function unlockTables(): void {
        if (!$this->tablesLocked) {
            return;
        }

        if ($this->pdo->inTransaction()) {
            $this->pdo->commit(); // implicit commit safety
        }

        $this->pdo->exec('UNLOCK TABLES');

        $this->tablesLocked = false;
        $this->lockedTables = [];
        $this->transactionLevel = 0;
        $this->lastTransactionStart = 0;
    }

    public function hasLockedTables(): bool {
        return $this->tablesLocked;
    }

    /* ---------------------------
      Global emergency cleanup
      ---------------------------- */

    public static function rollbackAll(): void {
        foreach (self::$instances as $db) {
            try {
                if ($db->tablesLocked) {
                    $db->unlockTables();
                } elseif ($db->isInTransaction()) {
                    $db->rollback();
                }
            } catch (Throwable $e) {
                error_log('PDODB cleanup failed: ' . $e->getMessage());
            }
        }
    }
}
