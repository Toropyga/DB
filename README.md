# DB

Database classes

![License](https://img.shields.io/badge/license-MIT-brightgreen.svg)
![Version](https://img.shields.io/badge/version-v3.1.1-blue.svg)
![PHP](https://img.shields.io/badge/php-v8-blueviolet.svg)

> The preferred PDO adapter name is `PDOLIB`. A temporary `PDO_LIB extends PDOLIB`
> compatibility wrapper is still available for applications migrating from v2.x;
> new code should use `Toropyga\DB\PDOLIB`.

## Content

- [General description](#General-description)
- [Changelog](#Changelog)
- [Installation](#Installation)
- [Requirements](#Requirements)
- [Configuration](#Configuration)
    - [Configuration constants MySQL](#Configuration-constants-MySQL)
    - [Configuration constants PostgreSQL](#Configuration-constants-PostgreSQL)
    - [Configuration constants ORACLE](#Configuration-constants-ORACLE)
    - [Configuration constants PDOLIB](#Configuration-constants-PDOLIB)
- [Work description](#Work-description)
    - [Including a class file](#Including-a-class-file)
    - [Classes initialisation](#Classes-initialisation)
    - [Getting a list of tables](#Getting-a-list-of-tables)
    - [Creating INSERT, DELETE and UPDATE queries from arrays](#Creating-INSERT-DELETE-and-UPDATE-queries-from-arrays)
    - [Sending a request](#Sending-a-request)

## General description

The library includes 4 main adapters:

1. MySQL - class for working with MySQL database.
2. PostgreSQL - class for working with PostgreSQL database through `ext-pgsql`.
3. Oracle - class for working with Oracle database.
4. PDOLIB - a generic class that uses the PDO library, including PostgreSQL and SQLite.

Functions are standardized in all libraries.

See [CHANGELOG.md](CHANGELOG.md) for release history and unreleased changes.

## Changelog

Release history is maintained in [CHANGELOG.md](CHANGELOG.md).

All adapters implement `DatabaseAdapterInterface`. Use `getQuery()` (or the
adapter's prepared execution methods) for user-controlled values. The
`getInsertSQL()`, `getUpdateSQL()`, and `getDeleteSQL()` methods only generate
SQL text for inspection or integration with external tooling.

Errors are written to the adapter log. When the `*_ERROR_EXIT` option or
`setErrorExit(true)` is enabled, a `DatabaseException` is thrown instead of
terminating the application or rendering HTML.

## Installation

The recommended way to install the DB library is using [Composer](http://getcomposer.org/):

```bash
composer require toropyga/db
```

## Requirements

- PHP 8.1 or newer.
- `ext-pdo` for `PDOLIB`.
- `ext-mysqli` for `MySQL`.
- `ext-pgsql` for `PostgreSQL`.
- `ext-oci8` for `Oracle` and PDO Oracle connections.
- `ext-pdo_pgsql` for PostgreSQL connections through `PDOLIB`.
- `ext-pdo_sqlite` for SQLite connections through `PDOLIB`.
- `ext-json` when array values are encoded for SQL parameters.

Only install and enable the extensions required by the adapter you use.

### Backwards-incompatible API changes

The current development version tightens parameter types on several public
methods. Passing an invalid value that previously returned `false` can now
raise `TypeError`. The affected methods are:

- `MySQL::getListFields(string $table)`
- `MySQL::setInsert(string $table, array $values)`
- `PDOLIB::prepare(string $sql, array $values = [...])`
- `PDOLIB::getListFields(string $table)`
- `Oracle::getProcedureQuery(string $package, string $procedure, ...)`

Validate arguments before calling these methods and update integrations that
relied on the old permissive behavior. This is an intentional
backwards-incompatible API change; see [CHANGELOG.md](CHANGELOG.md).

### Support matrix

The PHP column describes the declared language compatibility from `composer.json`.
Database-driver combinations require both `ext-pdo` and the matching PDO driver.

| Adapter / driver | PHP 8.1+ | Required extensions | Status |
| --- | --- | --- | --- |
| `MySQL` | Yes | `ext-mysqli` | Declared support |
| `PostgreSQL` | Yes | `ext-pgsql` | Declared support |
| `Oracle` | Yes | `ext-oci8` | Declared support |
| `PDOLIB` + `mysql` | Yes | `ext-pdo`, `ext-pdo_mysql` | Declared support |
| `PDOLIB` + `pgsql` | Yes | `ext-pdo`, `ext-pdo_pgsql` | Declared support |
| `PDOLIB` + `oci` | Yes | `ext-pdo`, `ext-pdo_oci` | Declared support |
| `PDOLIB` + `odbc` | Yes | `ext-pdo`, `ext-pdo_odbc` | Declared support |
| `PDOLIB` + `sqlite` | Yes | `ext-pdo`, `ext-pdo_sqlite` | Declared support |
| JSON array values | Yes | `ext-json` | Required only when arrays are encoded |

The matrix is a compatibility declaration, not a replacement for integration
tests against each database server and driver version.

## Configuration
Pre-setting of default parameters can be done directly in the class itself or using a named constant. 
Named constants are declared when the class is called, for example in a configuration file, and define default parameters.

### Configuration constants PostgreSQL
```php
const DB_PGSQL_HOST = '127.0.0.1';  // PostgreSQL server name or address
const DB_PGSQL_PORT = 5432;         // PostgreSQL server port
const DB_PGSQL_NAME = 'database';    // Database name
const DB_PGSQL_USER = 'user';        // User name
const DB_PGSQL_PASS = 'password';    // User password
const DB_PGSQL_STORAGE = true;       // Keep connection for the session
const DB_PGSQL_DEBUG = false;        // Enable or disable debugging
const DB_PGSQL_ERROR_EXIT = false;   // Throw DatabaseException on errors
const DB_PGSQL_LOG_NAME = 'db.log';  // Log file name
const DB_PGSQL_LOG_ALL = true;       // Log all actions or only errors
```

### Configuration constants MySQL
```php
const DB_MYSQL_HOST = '127.0.0.1';  // MySQL server name or address
const DB_MYSQL_PORT = 3306;         // MySQL server port
const DB_MYSQL_NAME = 'database';    // DB name
const DB_MYSQL_USER = 'user';        // User name
const DB_MYSQL_PASS = 'password';    // User password
const DB_MYSQL_STORAGE = true;       // Maintain connection for entire session
const DB_MYSQL_USE_TRANSACTION = true; // Use transaction
const DB_MYSQL_DEBUG = false;        // Enable or disable debugging features
const DB_MYSQL_ERROR_EXIT = false;   // Throw DatabaseException if an error occurs
const DB_MYSQL_LOG_NAME = 'db.log';  // Log file name
const DB_MYSQL_LOG_ALL = true;       // Log all actions (true) or only errors (false)
```
### Configuration constants ORACLE
```php
const DB_ORACLE_HOST = 'db.example'; // Oracle server name or address
const DB_ORACLE_PORT = 1521;        // Oracle server port
const DB_ORACLE_NAME = 'service';   // DB name
const DB_ORACLE_USER = 'user';       // User name
const DB_ORACLE_PASS = 'password';   // User password
const DB_ORACLE_STORAGE = true;     // Maintain connection for entire session
const DB_ORACLE_CHARSET = 'AL32UTF8'; // Charset
const DB_ORACLE_DEBUG = false;      // Enable or disable debugging features
const DB_ORACLE_ERROR_EXIT = false; // Throw DatabaseException if an error occurs
const DB_ORACLE_LOG_NAME = 'db.log'; // Log file name
const DB_ORACLE_LOG_ALL = true;     // Log all actions (true) or only errors (false)
const DB_ORACLE_USE_HOST = 2;       // Connection record type:
                                    //  0 - only the DB name is used
                                    //  1 - host and DB name is used
                                    //  2 - full entry is used for connection
```
### Configuration constants PDOLIB
```php
const DB_PDO_TYPE = 'mysql';        // DB type ['mysql', 'pgsql', 'oci', 'odbc', 'sqlite']
const DB_PDO_HOST = '127.0.0.1';    // DB server name or address
const DB_PDO_PORT = 3306;           // DB server port
const DB_PDO_NAME = 'database';     // DB name
const DB_PDO_USER = 'user';          // User name
const DB_PDO_PASS = 'password';      // User password
const DB_PDO_DEBUG = false;          // Enable or disable debugging features
const DB_PDO_ERROR_EXIT = false;     // Throw DatabaseException if an error occurs
const DB_PDO_ORACLE_CONNECT_TYPE = 2; // Oracle connection record type:
                                    //  0 - only the DB name is used
                                    //  1 - host and DB name is used
                                    //  2 - full entry is used for connection
```

## Work description

### Including a class file
```php
require_once("vendor/autoload.php");
```
---
### Classes initialisation
```php
$MYSQL = new Toropyga\DB\MySQL();
$POSTGRESQL = new Toropyga\DB\PostgreSQL();
$ORACLE = new Toropyga\DB\Oracle();
$PDO = new Toropyga\DB\PDOLIB();
```
or
```php
/**
 * DBMySQL constructor.
 * Class for working with MySQL database
 * @param mixed $HOST - host
 * @param mixed $PORT - port
 * @param mixed $NAME - DB name
 * @param mixed $USER - user name
 * @param mixed $PASS - user password
 */
$MYSQL = new Toropyga\DB\MySQL($HOST, $PORT, $NAME, $USER, $PASS);

/**
 * PostgreSQL constructor.
 * @param string $HOST - host
 * @param int|string $PORT - port
 * @param string $NAME - database name
 * @param string $USER - user name
 * @param string $PASS - password
 */
$POSTGRESQL = new Toropyga\DB\PostgreSQL($HOST, $PORT, $NAME, $USER, $PASS);

/**
 * DBOracle constructor.
 * @param string $HOST - host
 * @param string $NAME - DB name
 * @param string $USER - user name
 * @param string $PASS - user password
 * @param int $USE_HOST - the type of record used to connect to Oracle (takes a value of 0, 1 or 2), optimally 2
 * @param string $PORT - port
 * @param bool $P_CONNECT - maintain connection for entire session or connect on every SQL query
 * @param string $CHARSET - charset (default not set)
 * @param bool $no_connect - don't connect to DB when class is initiated (default - false, connects)
 */
$ORACLE = new Toropyga\DB\Oracle($HOST, $NAME, $USER, $PASS, $USE_HOST, $PORT, $P_CONNECT, $CHARSET, $no_connect);

/**
 * PDOLIB constructor.
 * @param string $db_type - DB type ['mysql', 'pgsql', 'oci', 'odbc', 'sqlite']
 * @param string $NAME - DB name
 * @param string $USER - user name
 * @param string $PASS - user password
 * @param string $HOST - host
 * @param string $PORT - port
 * @param string $oracle_connect_type - the type of record used to connect to Oracle:
 *      0 - only the DB name is used
 *      1 - host and DB name is used
 *      2 - full entry is used for connection
 */
$PDO = new Toropyga\DB\PDOLIB($db_type, $NAME, $USER, $PASS, $HOST, $PORT, $oracle_connect_type);
```
---
### Getting a list of tables
```php
$tables1 = $MYSQL->getTableList();
$tablesPostgreSQL = $POSTGRESQL->getTableList();
$tables2 = $ORACLE->getTableList();
$tables3 = $PDO->getTableList();
```
---
### Creating INSERT, DELETE and UPDATE queries from arrays
```php
$array = array('field1'=>'value1', 'field2'=>'value2', 'field3'=>'value3');
$index = array('field_where1'=>'value_where1', 'field_where2'=>'value_where2');
$sql_insert1 = $MYSQL->getInsertSQL('table_name', $array);
$sql_update1 = $MYSQL->getUpdateSQL('table_name', $array, $index);
$sql_delete1 = $MYSQL->getDeleteSQL('table_name', $index);

$sql_insertPostgreSQL = $POSTGRESQL->getInsertSQL('table_name', $array);
$sql_updatePostgreSQL = $POSTGRESQL->getUpdateSQL('table_name', $array, $index);
$sql_deletePostgreSQL = $POSTGRESQL->getDeleteSQL('table_name', $index);

$sql_insert2 = $ORACLE->getInsertSQL('table_name', $array);
$sql_update2 = $ORACLE->getUpdateSQL('table_name', $array, $index);
$sql_delete2 = $ORACLE->getDeleteSQL('table_name', $index);

$sql_insert3 = $PDO->getInsertSQL('table_name', $array);
$sql_update3 = $PDO->getUpdateSQL('table_name', $array, $index);
$sql_delete3 = $PDO->getDeleteSQL('table_name', $index);
```
### Sending a request
```php
$result1 = $MYSQL->getResults($sql, $one);
$result2 = $ORACLE->getResults($sql, $one);
$result3 = $PDO->getResults($sql, $one);
```

For parameterized SELECT queries, use `getQuery()` instead of concatenating
values into SQL. The adapter uses real bound parameters:

```php
$users = $PDO->getQuery(
    'SELECT id, name FROM users WHERE status = :status',
    ['status' => 'active'],
    'all'
);
```

`getInsertSQL()`, `getUpdateSQL()`, `getDeleteSQL()`, and `getQuerySQL()` build
SQL text for inspection or logging. For user-controlled values, prefer the
execution methods with bound parameters (`getQuery()`, `prepare()`/`execute()`)
where the adapter supports them.
Where:
* **$sql** - SQL query to DB
* **$one** - type of return 

**$one** can take values:
```
Numeric:
* 0 or '' - (selection: any number of rows and columns) expect an array of associative arrays ([] => array(field_name => value));
* 1 - (selection: one row / one column) expect a row, if the selection yielded more than one column - returns an associative array (field_name => value), if more than one row - returns an array of values ​] => value), if more than one row and more than one column - an array of associative arrays ([] => array(field_name => value));
* 2 - (selection: one row / many columns) expect an associative array (field_name => value), if more than one row and one column - returns an array of values ​] => value), if more than one row and more thgan one column - an array of associative arrays ([] => array(field_name => value));
* 3 - (selection: multiple rows / one column) expect an associative array of arrays (field_name => array([] => value), if more than one row and more than one column - an array of associative arrays ([] => array(field_name => value));
* 4 - (selection: multiple rows / one column) expect an array of values ​[] => value), if more than one row and more than one column - an array of associative arrays ([] => array(field_name => value)).
* 5 - (selection: multiple rows / 2 columns) expect an array of values ​value of field 1] => value of field 2)
* 6 - (selection: multiple rows / 2 columns) expect an array of values ​value of field 1] => value of field 2), if [value of field 1] is repeated, the array becomes [value of field 1] => array([0] => value of field 2, [1] => field value 2...)
* 7 - return data on query execution plan (EXPLAIN)

String (analogous to numeric):
* 'all' or '' - (selection: any number of rows and columns) expect an array of associative arrays ([] => array(field_name => value));
* 'one' - (selection: one row / one column) expect a row, if the selection yielded more than one column - returns an associative array (field_name => value), if more than one row - returns an array of values ​] => value), if more than one row and more than one column - an array of associative arrays ([] => array(field_name => value));
* 'row' - (selection: one row / many columns) expect an associative array (field_name => value), if more than one row and one column - returns an array of values ​] => value), if more than one row and more thgan one column - an array of associative arrays ([] => array(field_name => value));
* 'column' - (selection: multiple rows / one column) expect an associative array of arrays (field_name => array([] => value), if more than one row and more than one column - an array of associative arrays ([] => array(field_name => value));
* 'col' - (selection: multiple rows / one column) expect an array of values ​[] => value), if more than one row and more than one column - an array of associative arrays ([] => array(field_name => value)).
* 'dub' - (selection: multiple rows / 2 columns) expect an array of values [value of field 1] => value of field 2)
* 'dub_all' - (selection: multiple rows / 2 columns) expect an array of values [value of field 1] => value of field 2), if [value of field 1] is repeated, the array becomes [value of field 1] => array([0] => value of field 2, [1] => field value 2...)
* 'explain' - return data on query execution plan (EXPLAIN)
```

`6`/`'dub_all'` and `7`/`'explain'` are supported by all three classes, with one exception:

* **`Oracle`** has no single-statement `EXPLAIN`. `7`/`'explain'` runs
  `EXPLAIN PLAN FOR <sql>` followed by `SELECT ... FROM TABLE(DBMS_XPLAN.DISPLAY())`
  under the hood and returns the formatted plan as a flat array of text lines
  (rather than the structured row shape the other numeric modes return).
* **`PDOLIB`** behaves the same way for the `oci` driver type. For `mysql` and
  `pgsql` it simply runs the query with an `EXPLAIN ` prefix. For the `odbc`
  driver type, `'explain'` is **not supported** - there is no `EXPLAIN` syntax
  that is portable across ODBC backends - and calling it logs a message and
  returns an empty array instead of sending unpredictable SQL to the database.

You can also execute a query without processing the result (UPDATE, INSERT, etc.):
```php
$MYSQL->query($sql);
```

For Oracle, ordinary SELECT statements are executed through the parsed
statement. Enable `setCursor(true)` only when the PL/SQL request uses an OUT
cursor such as `:res`; regular SELECT requests do not require cursor mode.

`getListFields()` returns a list of column names. Metadata lookup failures
return `false` and do not reuse a stale prepared statement or connection.

Use `NULL` or `NOT NULL` as a condition value to generate `IS NULL` or
`IS NOT NULL`, for example: `['deleted_at' => 'NULL']`.
