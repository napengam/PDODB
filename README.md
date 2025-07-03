
# PDODB - PHP PDO Database Wrapper

![PHP Version](https://img.shields.io/badge/PHP-8.0%2B-blue.svg)
[![License](https://img.shields.io/badge/License-MIT-green.svg)](https://opensource.org/licenses/MIT)

A lightweight, singleton PDO wrapper class for secure and efficient database operations in PHP.

## Features

- Singleton pattern ensures single database connection per configuration
- Secure prepared statements by default
- Simple query execution with parameter binding
- Error handling with exceptions
- Connection pooling for multiple databases
- Lightweight with minimal overhead

## Installation

Simply include the class file in your project:

### Basic Setup

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

| Method | Description |
|--------|-------------|
| `getInstance(array $cfg)` | Gets singleton instance for given config |
| `query(string\|PDOStatement $sql, array $params)` | Executes query and returns results |
| `queryPrepare(string\|PDOStatement $sql)` | Prepares statement for later execution |
| `lastInsertId()` | Returns last inserted ID |
| `getLastRowCount()` | Returns number of rows affected by last query |
| `getPDO()` | Returns raw PDO instance (for advanced use) |

## Requirements

- PHP 8.0 or higher
- PDO extension with MySQL driver

## Security

- Uses prepared statements exclusively
- Errors are logged but not displayed to users
- Follows PHP best practices for database access

## Download

[Download PDODB.php](PDODB.php) (right-click and "Save link as")

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.