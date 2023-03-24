# DB

Классы для работы с базами данных

![License](https://img.shields.io/badge/license-MIT-brightgreen.svg)
![Version](https://img.shields.io/badge/version-v1.1.9-blue.svg)
![PHP](https://img.shields.io/badge/php-v5.1_--_v8-blueviolet.svg)

## Содержание

- [Общее описание](#Общее-описание)
- [Установка](#Установка)
- [Настройка](#Настройка)
    - [Настроечные константы MySQL](#Настроечные-константы-MySQL)
    - [Настроечные константы ORACLE](#Настроечные-константы-ORACLE)
    - [Настроечные константы PDO_LIB](#Настроечные-константы-PDO_LIB)
- [Описание работы](#описание-работы)
    - [Подключение файла класса](#Подключение-файла-класса)
    - [Инициализация классов](#Инициализация-классов)
    - [Получение списка таблиц](#Получение-списка-таблиц)
    - [Формирование запроса INSERT, DELETE и UPDATE из массивов](#Формирование-запроса-INSERT,-DELETE-и-UPDATE-из-массивов)
    - [Отправка запроса](#Отправка-запроса)

## Общее описание

В библиотеку входит 3 основных класса:

1. MySQL - класс для работы с БД MySQL.
2. Oracle - класс для работы с БД Oracle.
3. PDO_LIB - универсальный класс, использующий библиотеку PDO.

Функции во всех библиотеках стандартизованы.

## Установка

Рекомендуемый способ установки библиотеки FLog с использованием [Composer](http://getcomposer.org/):

```bash
composer require toropyga/db
```

## Настройка
Предварительная настройка параметров по умолчанию может осуществляться или непосредственно в самом классе, или с помощью именованных констант.
Именованные константы при необходимости объявляются до вызова класса, например, в конфигурационном файле, и определяют параметры по умолчанию

### Настроечные константы MySQL
```php
const DB_MYSQL_HOST;                // Имя/адрес сервера БД
const DB_MYSQL_PORT;                // Порт сервера
const DB_MYSQL_NAME;                // Имя базы данных
const DB_MYSQL_USER;                // Имя пользователя
const DB_MYSQL_PASS;                // Пароль пользователя
const DB_MYSQL_STORAGE;             // Сохранять подключение на весь сеанс или подключаться при каждом SQL-запросе
const DB_MYSQL_USE_TRANSACTION;     // Использовать постоянное подключение
const DB_MYSQL_DEBUG;               // Включить или отключить отладочные функции
const DB_MYSQL_ERROR_EXIT;          // Завершить ли работу программы при ошибке
const DB_MYSQL_LOG_NAME;            // Имя файла логов
const DB_MYSQL_LOG_ALL;             // Записывать в лог все действия (true) или только ошибки (false)
```
### Настроечные константы ORACLE
```php
const DB_ORACLE_HOST;               // Имя/адрес сервера БД
const DB_ORACLE_PORT;               // Порт сервера Oracle
const DB_ORACLE_NAME;               // Имя базы данных
const DB_ORACLE_USER;               // Имя пользователя
const DB_ORACLE_PASS;               // Пароль пользователя
const DB_ORACLE_STORAGE;            // Сохранять подключение на весь сеанс или подключаться при каждом SQL-запросе
const DB_ORACLE_CHARSET;            // Кодировка
const DB_ORACLE_DEBUG;              // Включить или отключить отладочные функции
const DB_ORACLE_ERROR_EXIT;         // Завершить ли работу программы при ошибке
const DB_ORACLE_LOG_NAME;           // Имя файла логов
const DB_ORACLE_LOG_ALL;            // Записывать в лог все действия (true) или только ошибки (false)
const DB_ORACLE_USE_HOST;           // Тип используемой записи для подключения к Oracle (принимает значение 0, 1 или 2), оптимально 2:
                                    //  0 - используется только имя базы данных
                                    //  1 - используется хост и имя базы данных
                                    //  2 - используется полная запись для подключения
```
### Настроечные константы PDO_LIB
```php
const DB_PDO_TYPE;                  // Тип БД ['mysql', 'pgsql', 'oci', 'odbc']
const DB_PDO_HOST;                  // Имя/адрес сервера БД
const DB_PDO_PORT;                  // Порт сервера
const DB_PDO_NAME;                  // Имя базы данных
const DB_PDO_USER;                  // Имя пользователя
const DB_PDO_PASS;                  // Пароль пользователя
const DB_PDO_DEBUG;                 // Включить или отключить отладочные функции
const DB_PDO_ERROR_EXIT;            // Завершить ли работу программы при ошибке
const DB_PDO_ORACLE_CONNECT_TYPE;   // Тип используемой записи для подключения к Oracle (принимает значение 0, 1 или 2), оптимально 2:
                                    //  0 - используется только имя базы данных
                                    //  1 - используется хост и имя базы данных
                                    //  2 - используется полная запись для подключения
```

## Описание работы

### Подключение файла класса
```php
require_once("vendor/autoload.php");
```
---
### Инициализация классов
```php
$MYSQL = new FYN\DB\MySQL();
$ORACLE = new FYN\DB\Oracle();
$PDO = new FYN\DB\PDO_LIB();
```
или
```php
/**
 * DBMySQL constructor.
 * Класс для работы с БД MySQL
 * @param mixed $HOST - хост
 * @param mixed $PORT - порт
 * @param mixed $NAME - имя БД
 * @param mixed $USER - пользователь
 * @param mixed $PASS - пароль
 */
$MYSQL = new FYN\DB\MySQL($HOST, $PORT, $NAME, $USER, $PASS);

/**
 * DBOracle constructor.
 * @param string $HOST - сервер
 * @param string $NAME - имя базы данных
 * @param string $USER - пользователь
 * @param string $PASS - пароль
 * @param int $USE_HOST - какая строка подклюения используется (принимает значение 0, 1 или 2) оптимально 2
 * @param string $PORT - порт
 * @param bool $P_CONNECT - использовать ли постоянное подключение
 * @param string $CHARSET - кодировка (по умолчанию - не указана)
 * @param bool $no_connect - не подключаться к БД при инициации класса (по умолчанию - false)
 */
$ORACLE = new FYN\DB\Oracle($HOST, $NAME, $USER, $PASS, $USE_HOST, $PORT, $P_CONNECT, $CHARSET, $no_connect);

/**
 * PDO_LIB constructor.
 * @param string $db_type - тип БД ['mysql', 'pgsql', 'oci', 'odbc']
 * @param string $HOST - сервер
 * @param string $NAME - имя базы данных
 * @param string $USER - пользователь
 * @param string $PASS - пароль
 * @param string $PORT - порт
 * @param string $oracle_connect_type - Тип используемой записи для подключения к Oracle:
 *      0 - используется только имя базы данных
 *      1 - используется хост и имя базы данных
 *      2 - используется полная запись для подключения
 */
$PDO = new FYN\DB\PDO_LIB($db_type, $NAME, $USER, $PASS, $HOST, $PORT, $oracle_connect_type);
```
---
### Получение списка таблиц
```php
$tables1 = $MYSQL->getTableList();
$tables2 = $ORACLE->getTableList();
$tables3 = $PDO->getTableList();
```
---
### Формирование запроса INSERT, DELETE и UPDATE из массивов
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
### Отправка запроса
```php
$result1 = $MYSQL->getResult($sql, $one);
$result2 = $ORACLE->getResult($sql, $one);
$result3 = $PDO->getResult($sql, $one);
```
Где:
* **$sql** - SQL запрос к БД
* **$one** - как вернуть результат 

**$one** может принимать значения:
```
Числовые:
* 0 или '' - (выборка: любое количество строк и столбцов) ожидаем массив ассоциативных массивов ([] => array(имя_поля => значение));
* 1 - (выборка: одна строка / один столбец) ожидаем строку, если при выборке получилось более одного столбца - возвращает ассоциативный массив (имя_поля => значение), если более одной строки - возвращает массив значений ([] => значение), если более одной строки и более одного столбца - массив ассоциативных массивов ([] => array(имя_поля => значение));
* 2 - (выборка: одна строка / множество столбцов) ожидаем ассоциативный массив (имя_поля => значение), если более одной строки и один столбец - возвращает массив значений ([] => значение), если более одной строки и более одного столбца - массив ассоциативных массивов ([] => array(имя_поля => значение));
* 3 - (выборка: множество строк / один столбец) ожидаем ассоциативный массив массивов (имя_поля => array([] => значение), если более одной строки и более одного столбца - массив ассоциативных массивов ([] => array(имя_поля => значение));
* 4 - (выборка: множество строк / один столбец) ожидаем массив значений ([] => значение), если более одной строки и более одного столбца - массив ассоциативных массивов ([] => array(имя_поля => значение)).
* 5 - (выборка: множество строк / 2 столбца) ожидаем массив значений ([значение поля 1] => значение поля 2)
* 6 - (выборка: множество строк / 2 столбца) ожидаем массив значений ([значение поля 1] => значение поля 2), если [значение поля 1] повторяется, то массив принимает вид [значение поля 1] => array([0] => значение поля 2, [1] => значение поля 2...)
* 7 - возврат данных по выполнению запроса (EXPLAIN)

Строковые (аналог числовых):
* 'all' или '' - (выборка: любое количество строк и столбцов) ожидаем массив ассоциативных массивов ([] => array(имя_поля => значение));
* 'one' - (выборка: одна строка / один столбец) ожидаем строку, если при выборке получилось более одного столбца - возвращает ассоциативный массив (имя_поля => значение), если более одной строки - возвращает массив значений ([] => значение), если более одной строки и более одного столбца - массив ассоциативных массивов ([] => array(имя_поля => значение));
* 'row' - (выборка: одна строка / множество столбцов) ожидаем ассоциативный массив (имя_поля => значение), если более одной строки и один столбец - возвращает массив значений ([] => значение), если более одной строки и более одного столбца - массив ассоциативных массивов ([] => array(имя_поля => значение));
* 'column' - (выборка: множество строк / один столбец) ожидаем ассоциативный массив массивов (имя_поля => array([] => значение), если более одной строки и более одного столбца - массив ассоциативных массивов ([] => array(имя_поля => значение));
* 'col' - (выборка: множество строк / один столбец) ожидаем массив значений ([] => значение), если более одной строки и более одного столбца - массив ассоциативных массивов ([] => array(имя_поля => значение)).
* 'dub' - (выборка: множество строк / 2 столбца) ожидаем массив значений ([значение поля 1] => значение поля 2)
* 'dub_all' - (выборка: множество строк / 2 столбца) ожидаем массив значений ([значение поля 1] => значение поля 2), если [значение поля 1] повторяется, то массив принимает вид [значение поля 1] => array([0] => значение поля 2, [1] => значение поля 2...)
* 'explain' - возврат данных по выполнению запроса (EXPLAIN)
```
А также можно выполнить запрос без обработки результата (UPDATE, INSERT и т.д.):
```php
$MYSQL->query($sql);
```