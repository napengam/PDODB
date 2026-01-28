# PDODB

PDODB is a **lightweight, SQL-first database abstraction layer** built
on top of PHP PDO. It is intentionally **not an ORM**. Instead, it
provides a thin, safe, and performant layer around PDO with predictable
behavior and strong transactional guarantees.

------------------------------------------------------------------------

## Philosophy

-   SQL stays SQL --- no query builders, no magic
-   PDO is used directly, but safely
-   Transactions and locks are **explicit and trackable**
-   Errors must never leave open transactions or locked tables
-   One connection = one PDODB instance
-   Designed for **InnoDB** and real-world concurrency

------------------------------------------------------------------------

## Features

-   PDO-based (MySQL / MariaDB)
-   SQL-first API
-   Statement caching (LRU, hash-based)
-   Named parameter support
-   Multiple fetch modes (`object`, `assoc`, `both`, `num`, `column`)
-   Transaction state tracking per connection
-   Table lock tracking & safe unlock
-   Global rollback safety (`rollbackAll()`)
-   `public_id` helper with retry-safe upserts
-   Zero dependencies

------------------------------------------------------------------------

## Requirements

-   PHP 8.1+
-   PDO extension
-   MySQL / MariaDB
-   InnoDB storage engine (required for transactions)

------------------------------------------------------------------------

## Installation

Copy `PDODB.php` into your project and include it.

``` php
require 'PDODB.php';
```

------------------------------------------------------------------------

## Configuration

``` php
$config = [
    'default' => [
        'host' => 'localhost',
        'dbname' => 'testdb',
        'user' => 'root',
        'password' => ''
    ]
];
```

------------------------------------------------------------------------

## Getting an Instance

``` php
$db = PDODB::getInstance('default', $config);
```

Each alias returns **one shared connection instance**.

------------------------------------------------------------------------

## Basic Queries

``` php
$rows = $db->query("SELECT * FROM users");
```

With parameters:

``` php
$user = $db->queryFetchOne(
    "SELECT * FROM users WHERE id = :id",
    ['id' => 1]
);
```

------------------------------------------------------------------------

## Fetch Modes

``` php
$db->queryMode("SELECT * FROM users", 'object');
$db->queryMode("SELECT * FROM users", 'assoc');
$db->queryMode("SELECT * FROM users", 'both');
$db->queryMode("SELECT id FROM users", 'column');
```

Execute-only (no fetch):

``` php
$affected = $db->queryMode(
    "UPDATE users SET active=0",
    'exec'
);
```

------------------------------------------------------------------------

## Transactions

### Manual Control

``` php
$db->begin();

try {
    $db->query("UPDATE accounts SET balance=balance-100 WHERE id=1");
    $db->query("UPDATE accounts SET balance=balance+100 WHERE id=2");

    $db->commit();
} catch (Throwable $e) {
    $db->rollback();
    throw $e;
}
```

Transaction state is tracked internally.

------------------------------------------------------------------------

## Table Locks

``` php
$db->lockTables("LOCK TABLES users WRITE, orders READ");

try {
    $db->query("UPDATE users SET status='locked'");
} finally {
    $db->unlockTables();
}
```

PDODB tracks lock state per connection.

------------------------------------------------------------------------

## Global Safety Cleanup

If a **fatal error** or uncaught exception occurs, you can safely clean
**all connections**:

``` php
PDODB::rollbackAll();
```

This will: 1. Unlock tables (implicit commit safe) 2. Roll back active
transactions 3. Prevent dead connections holding locks

Designed to integrate with a global ErrorHandler.

------------------------------------------------------------------------

## insertPublicId()

Safely inserts or updates a row using a unique `public_id`.

Requirements: - Table must have a `public_id` column - `public_id` must
be UNIQUE

``` php
$id = $db->insertPublicId('users', [
    'name' => 'Alice',
    'email' => 'alice@example.com'
]);

$publicId = $db->lastPublicId();
```

Behavior: - Generates a secure random `public_id` if missing - Retries
automatically on duplicate key - Uses `ON DUPLICATE KEY UPDATE`

------------------------------------------------------------------------

## Statement Caching

Prepared statements are cached by normalized SQL hash.

-   Reduces prepare overhead
-   Safe per-connection
-   Simple LRU eviction (default: 100)

No behavioral change required --- transparent optimization.

------------------------------------------------------------------------

## Schema Assumptions

PDODB assumes:

-   InnoDB tables
-   Proper foreign keys
-   No implicit commits mid-transaction
-   No mixing transactional + non-transactional tables

------------------------------------------------------------------------

## What PDODB Is NOT

-   ❌ ORM
-   ❌ Query builder
-   ❌ Schema manager
-   ❌ Migration tool

PDODB handles **CRUD + safety**, nothing else.

------------------------------------------------------------------------

## Similar Libraries

-   PDO (raw)
-   Doctrine DBAL (heavier)
-   Laminas DB

PDODB sits between **raw PDO** and **full DBAL**.

------------------------------------------------------------------------

## License

MIT License

------------------------------------------------------------------------

## Final Notes

PDODB is intentionally small and explicit. If you need magic, use an
ORM. If you need **control, safety, and performance**, use PDODB.
