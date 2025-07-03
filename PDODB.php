<?php

class PDODB {

    private PDO $pdo;
    private int $lastRowCount;
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
            error_log("Database connection failed: " . $e->getMessage());
            throw new Exception("Database connection failed.");
        }
        $this->realDBName = $cfg['dbname'];
    }

    /**
     * Static factory method to get the instance
     */
    public static function getInstance($cfg): self {
        foreach (['host', 'dbname', 'user', 'password'] as $key) {
            if (empty($cfg[$key])) {
                throw new InvalidArgumentException("Missing DB config key: $key");
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
            error_log("Query execution failed: " . $e->getMessage());
            throw new Exception("Query execution failed.");
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
                error_log("Prepare for $sql failed: " . $e->getMessage());
                throw new Exception("Query execution failed.");
            }
        }
        return $stmt;
    }
}

$mitglieder = [
    'host' => 'localhost',
    'dbname' => 'v092997',
    'user' => 'root',
    'password' => 'hgs123'
];

$x = PDODB::getInstance($mitglieder);
$r = $x->query("select * from mitglieder where name like ?", ["%hö%"]);
echo count($r);
$r = $x->getLastRowCount();
echo $r;