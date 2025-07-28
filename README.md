
# PDODB - PHP PDO Database Wrapper

![PHP Version](https://img.shields.io/badge/PHP-8.0%2B-blue.svg)
[![License](https://img.shields.io/badge/License-MIT-green.svg)](https://opensource.org/licenses/MIT)

A lightweight, secure PDO database wrapper for MySQL with connection pooling, transaction support, and table locking capabilities.

## Features

- Singleton pattern for database instances
- Prepared statements by default
- Transaction management (begin, commit, rollback)
- Table locking/unlocking
- Error handling with automatic rollback
- Configurable fetch mode (defaults to objects)
- Connection pooling by database name

## Installation

1. Ensure you have PHP 8.0+ with PDO MySQL extension enabled
2. Include the PDODB class in your project:

```php
require_once 'PDODB.php';

$dbConfig = [
    'host' => 'localhost',
    'dbname' => 'your_database',
    'user' => 'db_user',
    'password' => 'secure_password'
];
```
### Querying Data
```php
// Get database instance
$db = PDODB::getInstance($dbConfig);

// Simple query
$results = $db->query("SELECT * FROM users WHERE active = ?", [1]);

// With prepared statement
$stmt = $db->queryPrepare("SELECT * FROM products WHERE category = ?");
$products = $db->query($stmt, ['electronics']);
```
### Getting Results

```php
// Get all rows
$users = $db->query("SELECT * FROM users");
foreach ($users as $user) {
    echo $user->name;
}

// Get row count
$count = $db->getLastRowCount();
echo "Found $count users";

// Get last insert ID
$id = $db->lastInsertId();
```
## Methods

| Method                 | Description                               |
| ---------------------- | ----------------------------------------- |
| `getInstance($cfg)`    | Get or create a DB instance per config    |
| `getPDO()`             | Get raw PDO connection                    |
| `query($sql, $params)` | Run query and return all rows             |
| `getEmptyRecord($tableName)` | Return a empty record as object; no insert done     |
| `queryPrepare($sql)`   | Return a prepared PDOStatement            |
| `lastInsertId()`       | Get last auto-increment ID                |
| `getLastRowCount()`    | Get number of rows affected by last query |
| `begin()`              | Start transaction                         |
| `commit()`             | Commit transaction                        |
| `rollback()`           | Roll back transaction                     |
| `lockTables($sql)`     | Lock tables using SQL `LOCK TABLES`       |
| `unlockTables()`       | Unlock previously locked tables           |

## Requirements

- PHP 8.0 or higher
- PDO extension with MySQL driver


## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.