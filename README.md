# DB

Database classes

![License](https://img.shields.io/badge/license-MIT-brightgreen.svg)
![Version](https://img.shields.io/badge/version-v1.2.0-blue.svg)
![PHP](https://img.shields.io/badge/php-v5.1_--_v8-blueviolet.svg)

## Content

- [General description](#General-description)
- [Installation](#Installation)
- [Configuration](#Configuration)
    - [Configuration constants MySQL](#Configuration-constants-MySQL)
    - [Configuration constants ORACLE](#Configuration-constants-ORACLE)
    - [Configuration constants PDO_LIB](#Configuration-constants-PDO_LIB)
- [Work description](#Work-description)
    - [Including a class file](#Including-a-class-file)
    - [Classes initialisation](#Classes-initialisation)
    - [Getting a list of tables](#Getting-a-list-of-tables)
    - [Creating INSERT, DELETE and UPDATE queries from arrays](#Creating-INSERT,-DELETE-and-UPDATE-queries-from-arrays)
    - [Sending a request](#Sending-a-request)

## General description

The library includes 3 main classes:

1. MySQL - class for working with MySQL database.
2. Oracle - class for working with Oracle database.
3. PDO_LIB - a generic class that uses the PDO library.

Functions are standardized in all libraries.

## Installation

The recommended way to install the DB library is using [Composer](http://getcomposer.org/):

```bash
composer require toropyga/db
```

## Configuration
Pre-setting of default parameters can be done directly in the class itself or using a named constant. 
Named constants are declared when the class is called, for example in a configuration file, and define default parameters.

### Configuration constants MySQL
```php
const DB_MYSQL_HOST;                // MySQL server name or address
const DB_MYSQL_PORT;                // MySQL server port
const DB_MYSQL_NAME;                // DB name
const DB_MYSQL_USER;                // User name
const DB_MYSQL_PASS;                // User password
const DB_MYSQL_STORAGE;             // Maintain connection for entire session or connect on every SQL query
const DB_MYSQL_USE_TRANSACTION;     // Use transaction
const DB_MYSQL_DEBUG;               // Enable or disable debugging features
const DB_MYSQL_ERROR_EXIT;          // Terminate the program if an error occurs
const DB_MYSQL_LOG_NAME;            // Log file name
const DB_MYSQL_LOG_ALL;             // Log all actions (true) or only errors (false)
```
### Configuration constants ORACLE
```php
const DB_ORACLE_HOST;               // Oracle server name or address
const DB_ORACLE_PORT;               // Oracle server port
const DB_ORACLE_NAME;               // DB name
const DB_ORACLE_USER;               // User name
const DB_ORACLE_PASS;               // User password
const DB_ORACLE_STORAGE;            // Maintain connection for entire session or connect on every SQL query
const DB_ORACLE_CHARSET;            // Charset
const DB_ORACLE_DEBUG;              // Enable or disable debugging features
const DB_ORACLE_ERROR_EXIT;         // Terminate the program if an error occurs
const DB_ORACLE_LOG_NAME;           // Log file name
const DB_ORACLE_LOG_ALL;            // Log all actions (true) or only errors (false)
const DB_ORACLE_USE_HOST;           // The type of record used to connect to Oracle (takes a value of 0, 1 or 2), optimally 2:
                                    //  0 - only the DB name is used
                                    //  1 - host and DB name is used
                                    //  2 - full entry is used for connection
```
### Configuration constants PDO_LIB
```php
const DB_PDO_TYPE;                  // DB type ['mysql', 'pgsql', 'oci', 'odbc']
const DB_PDO_HOST;                  // DB server name or address
const DB_PDO_PORT;                  // DB Server port
const DB_PDO_NAME;                  // DB name
const DB_PDO_USER;                  // User name
const DB_PDO_PASS;                  // User password
const DB_PDO_DEBUG;                 // Enable or disable debugging features
const DB_PDO_ERROR_EXIT;            // Terminate the program if an error occurs
const DB_PDO_ORACLE_CONNECT_TYPE;   // The type of record used to connect to Oracle (takes a value of 0, 1 or 2), optimally 2:
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
$MYSQL = new FYN\DB\MySQL();
$ORACLE = new FYN\DB\Oracle();
$PDO = new FYN\DB\PDO_LIB();
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
$MYSQL = new FYN\DB\MySQL($HOST, $PORT, $NAME, $USER, $PASS);

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
$ORACLE = new FYN\DB\Oracle($HOST, $NAME, $USER, $PASS, $USE_HOST, $PORT, $P_CONNECT, $CHARSET, $no_connect);

/**
 * PDO_LIB constructor.
 * @param string $db_type - DB type ['mysql', 'pgsql', 'oci', 'odbc']
 * @param string $HOST - host
 * @param string $NAME - DB name
 * @param string $USER - user name
 * @param string $PASS - user password
 * @param string $PORT - port
 * @param string $oracle_connect_type - the type of record used to connect to Oracle:
 *      0 - only the DB name is used
 *      1 - host and DB name is used
 *      2 - full entry is used for connection
 */
$PDO = new FYN\DB\PDO_LIB($db_type, $NAME, $USER, $PASS, $HOST, $PORT, $oracle_connect_type);
```
---
### Getting a list of tables
```php
$tables1 = $MYSQL->getTableList();
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
$sql_delete1 = $MYSQL->getDeleteSQL('table_name', $array, $index);

$sql_insert2 = $ORACLE->getInsertSQL('table_name', $array);
$sql_update2 = $ORACLE->getUpdateSQL('table_name', $array, $index);
$sql_delete2 = $ORACLE->getDeleteSQL('table_name', $array, $index);

$sql_insert3 = $PDO->getInsertSQL('table_name', $array);
$sql_update3 = $PDO->getUpdateSQL('table_name', $array, $index);
$sql_delete3 = $PDO->getDeleteSQL('table_name', $array, $index);
```
### Sending a request
```php
$result1 = $MYSQL->getResult($sql, $one);
$result2 = $ORACLE->getResult($sql, $one);
$result3 = $PDO->getResult($sql, $one);
```
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
* 7 - return data on query execution EXPLAIN

String (analogous to numeric):
* 'all' or '' - (selection: any number of rows and columns) expect an array of associative arrays ([] => array(field_name => value));
* 'one' - (selection: one row / one column) expect a row, if the selection yielded more than one column - returns an associative array (field_name => value), if more than one row - returns an array of values ​] => value), if more than one row and more than one column - an array of associative arrays ([] => array(field_name => value));
* 'row' - (selection: one row / many columns) expect an associative array (field_name => value), if more than one row and one column - returns an array of values ​] => value), if more than one row and more thgan one column - an array of associative arrays ([] => array(field_name => value));
* 'column' - (selection: multiple rows / one column) expect an associative array of arrays (field_name => array([] => value), if more than one row and more than one column - an array of associative arrays ([] => array(field_name => value));
* 'col' - (selection: multiple rows / one column) expect an array of values ​[] => value), if more than one row and more than one column - an array of associative arrays ([] => array(field_name => value)).
* 'dub' - (selection: multiple rows / 2 columns) expect an array of values [value of field 1] => value of field 2)
* 'dub_all' - (selection: multiple rows / 2 columns) expect an array of values [value of field 1] => value of field 2), if [value of field 1] is repeated, the array becomes [value of field 1] => array([0] => value of field 2, [1] => field value 2...)
* 'explain' - return data on query execution EXPLAIN
```
You can also execute a query without processing the result (UPDATE, INSERT, etc.):
```php
$MYSQL->query($sql);
```