<?php

class PDODB {

    private PDO $pdo;
    private int $lastRowCount;
    private bool $isLocked;
    private string $realDBName = '';
    // store instances per dbAlias
    private static array $instances = [];

    // private constructor: can't be called from outside directly
    private function __construct($cfg) {

        $dsn = "mysql:host={$cfg['host']};dbname={$cfg['dbname']};charset=utf8";
        $opt = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ
        ];

        try {
            $this->pdo = new PDO($dsn, $cfg['user'], $cfg['password'], $opt);
            $this->pdo->exec("SET SESSION sql_mode = 'NO_ZERO_DATE,NO_ZERO_IN_DATE'");
        } catch (PDOException $e) {
            $this->handleError('query', $e);
        }
        $this->realDBName = $cfg['dbname'];
    }

    /**
     * Static factory method to get the instance
     */
    public static function getInstance($cfg): self {
        foreach (['host', 'dbname', 'user', 'password'] as $key) {
            if (empty($cfg[trim($key)])) {
                throw new InvalidArgumentException("Missing DB config key: '$key'");
            }
        }
        if (!isset(self::$instances[$cfg['dbname']])) {
            self::$instances[$cfg['dbname']] = new self($cfg);
        }
        return self::$instances[$cfg['dbname']];
    }

    public function getPDO(): PDO {
        return $this->pdo;
    }

    public function queryPrepare(string|PDOStatement $sql): PDOStatement {
        return $this->isSqlOrStatement($sql);
    }

    public function query(string|PDOStatement $sql, $params = []): array {
        try {
            $stmt = $this->isSqlOrStatement($sql);
            $stmt->execute($params);
            $this->lastRowCount = $stmt->rowCount();
            return $stmt->fetchAll() ?: [];
        } catch (PDOException $e) {
            $this->handleError('query', $e);
        }
    }

    public function lastInsertId(): string {
        return $this->pdo->lastInsertId();
    }

    public function getLastRowCount(): int {
        return $this->lastRowCount;
    }

    private function isSqlOrStatement(string|PDOStatement $sql): PDOStatement {
        if ($sql instanceof PDOStatement) {
            $stmt = $sql;
        } else {
            try {
                $stmt = $this->pdo->prepare($sql);
            } catch (PDOException $e) {
                $this->handleError('query', $e);
            }
        }
        return $stmt;
    }

    public function lockTables(string $lockSql): bool {
        if ($this->isLocked) {
            return true;
        }

        if (empty($lockSql)) {
            throw new InvalidArgumentException("Lock SQL cannot be empty.");
        }

        $result = $this->pdo->query($lockSql);
        if ($result !== false) {
            $this->isLocked = true;
        }
        return $this->isLocked;
    }

    public function unlockTables(): void {
        if ($this->isLocked === true) {
            $this->pdo->query("UNLOCK TABLES");
            $this->isLocked = false;
        }
    }

    public function begin(): void {
        $this->pdo->beginTransaction();
    }

    public function commit(): void {
        if ($this->pdo->inTransaction()) {
            $this->pdo->commit();
        }
    }

    public function rollback(): void {
        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
    }

    private function handleError(string $context, Exception $e): void {
        // Attempt rollback if in transaction
        try {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
        } catch (PDOException $ex) {
            error_log("Rollback failed during $context: " . $ex->getMessage());
        }

        // Attempt to unlock tables
        try {
            $this->pdo->exec("UNLOCK TABLES");
        } catch (PDOException $ex) {
            error_log("Unlock tables failed during $context: " . $ex->getMessage());
        }

        // Log original error
        error_log("DB ERROR during $context: " . $e->getMessage());

        // Throw sanitized error to caller
        throw new Exception("A database error occurred. Please try again later.");
    }
}
