# DB

Классы для работы с базами данных

![License](https://img.shields.io/badge/license-MIT-brightgreen.svg)
![Version](https://img.shields.io/badge/version-v3.2.0-blue.svg)
![PHP](https://img.shields.io/badge/php-v8-blueviolet.svg)

> Предпочтительное имя PDO-адаптера — `PDOLIB`. Для перехода с v2.x временно
> доступен совместимый класс-обертка `PDO_LIB extends PDOLIB`; в новом коде
> используйте `Toropyga\DB\PDOLIB`.

## Содержание

- [Общее описание](#Общее-описание)
- [История изменений](#История-изменений)
- [Установка](#Установка)
- [Требования](#Требования)
- [Настройка](#Настройка)
    - [Настроечные константы MySQL](#Настроечные-константы-MySQL)
    - [Настроечные константы PostgreSQL](#Настроечные-константы-PostgreSQL)
    - [Настроечные константы ORACLE](#Настроечные-константы-ORACLE)
    - [Настроечные константы PDOLIB](#Настроечные-константы-PDOLIB)
- [Описание работы](#описание-работы)
    - [Подключение файла класса](#Подключение-файла-класса)
    - [Инициализация классов](#Инициализация-классов)
    - [Получение списка таблиц](#Получение-списка-таблиц)
    - [Формирование запроса INSERT, DELETE и UPDATE из массивов](#Формирование-запроса-INSERT-DELETE-и-UPDATE-из-массивов)
    - [Отправка запроса](#Отправка-запроса)

## Общее описание

В библиотеку входит 4 основных адаптера:

1. MySQL - класс для работы с БД MySQL.
2. PostgreSQL - класс для работы с PostgreSQL через `ext-pgsql`.
3. Oracle - класс для работы с БД Oracle.
4. PDOLIB - универсальный класс на основе PDO, включая PostgreSQL и SQLite.

Функции во всех библиотеках стандартизованы.

История релизов и текущих изменений приведена в [CHANGELOG.md](CHANGELOG.md).

## История изменений

Подробная история релизов находится в [CHANGELOG.md](CHANGELOG.md).

Все адаптеры реализуют `DatabaseAdapterInterface`. Для пользовательских
значений используйте `getQuery()` или методы выполнения с prepared statements.
Методы `getInsertSQL()`, `getUpdateSQL()` и `getDeleteSQL()` только формируют
текст SQL для просмотра или передачи во внешние инструменты.

Ошибки записываются во внутренний лог адаптера. При включении параметра
`*_ERROR_EXIT` или вызове `setErrorExit(true)` выбрасывается
`DatabaseException`; приложение не завершается через `exit`, и HTML-ошибка
не выводится.

## Установка

Рекомендуемый способ установки библиотеки DB с использованием [Composer](http://getcomposer.org/):

```bash
composer require toropyga/db
```

## Требования

- PHP 8.1 или новее.
- `ext-pdo` для `PDOLIB`.
- `ext-mysqli` для `MySQL`.
- `ext-pgsql` для `PostgreSQL`.
- `ext-oci8` для `Oracle` и PDO-подключений к Oracle.
- `ext-pdo_pgsql` для PostgreSQL через `PDOLIB`.
- `ext-pdo_sqlite` для SQLite через `PDOLIB`.
- `ext-json`, если массивы передаются как значения SQL.

Подключайте только расширения, необходимые используемому адаптеру.

### Несовместимые изменения API

В текущей версии разработки для нескольких публичных методов усилена
типизация параметров. Передача некорректного значения, которая раньше могла
вернуть `false`, теперь может привести к `TypeError`. Изменения затрагивают:

- `MySQL::getListFields(string $table)`
- `MySQL::setInsert(string $table, array $values)`
- `PDOLIB::prepare(string $sql, array $values = [...])`
- `PDOLIB::getListFields(string $table)`
- `Oracle::getProcedureQuery(string $package, string $procedure, ...)`

Проверяйте аргументы до вызова этих методов и обновите интеграции, которые
использовали прежнее нестрогое поведение. Изменение является намеренно
обратно несовместимым; подробности приведены в [CHANGELOG.md](CHANGELOG.md).

### Матрица поддержки

Версия PHP соответствует заявлению в `composer.json`. Для PDO требуются
`ext-pdo` и соответствующий драйвер.

| Адаптер / драйвер | PHP 8.1+ | Требуемые расширения | Статус |
| --- | --- | --- | --- |
| `MySQL` | Да | `ext-mysqli` | Заявленная поддержка |
| `PostgreSQL` | Да | `ext-pgsql` | Заявленная поддержка |
| `Oracle` | Да | `ext-oci8` | Заявленная поддержка |
| `PDOLIB` + `mysql` | Да | `ext-pdo`, `ext-pdo_mysql` | Заявленная поддержка |
| `PDOLIB` + `pgsql` | Да | `ext-pdo`, `ext-pdo_pgsql` | Заявленная поддержка |
| `PDOLIB` + `oci` | Да | `ext-pdo`, `ext-pdo_oci` | Заявленная поддержка |
| `PDOLIB` + `odbc` | Да | `ext-pdo`, `ext-pdo_odbc` | Заявленная поддержка |
| `PDOLIB` + `sqlite` | Да | `ext-pdo`, `ext-pdo_sqlite` | Заявленная поддержка |
| JSON-значения массивов | Да | `ext-json` | Нужно только при кодировании массивов |

Матрица описывает совместимость по конфигурации проекта, но не заменяет
интеграционные тесты с конкретными версиями серверов и драйверов БД.

## Настройка
Предварительная настройка параметров по умолчанию может осуществляться или непосредственно в самом классе, или с помощью именованных констант.
Именованные константы при необходимости объявляются до вызова класса, например, в конфигурационном файле, и определяют параметры по умолчанию

### Настроечные константы PostgreSQL
```php
const DB_PGSQL_HOST = '127.0.0.1';  // Имя/адрес сервера PostgreSQL
const DB_PGSQL_PORT = 5432;         // Порт сервера PostgreSQL
const DB_PGSQL_NAME = 'database';    // Имя базы данных
const DB_PGSQL_USER = 'user';        // Имя пользователя
const DB_PGSQL_PASS = 'password';    // Пароль пользователя
const DB_PGSQL_STORAGE = true;       // Сохранять подключение на весь сеанс
const DB_PGSQL_DEBUG = false;        // Включить отладку
const DB_PGSQL_ERROR_EXIT = false;   // Выбрасывать DatabaseException при ошибке
const DB_PGSQL_LOG_NAME = 'db.log';  // Имя файла логов
const DB_PGSQL_LOG_ALL = true;       // Записывать все действия или только ошибки
```

### Настроечные константы MySQL
```php
const DB_MYSQL_HOST = '127.0.0.1';  // Имя/адрес сервера БД
const DB_MYSQL_PORT = 3306;         // Порт сервера
const DB_MYSQL_NAME = 'database';   // Имя базы данных
const DB_MYSQL_USER = 'user';       // Имя пользователя
const DB_MYSQL_PASS = 'password';   // Пароль пользователя
const DB_MYSQL_STORAGE = true;      // Сохранять подключение на весь сеанс
const DB_MYSQL_USE_TRANSACTION = true; // Использовать транзакции
const DB_MYSQL_DEBUG = false;       // Включить или отключить отладочные функции
const DB_MYSQL_ERROR_EXIT = false;  // Выбрасывать DatabaseException при ошибке
const DB_MYSQL_LOG_NAME = 'db.log'; // Имя файла логов
const DB_MYSQL_LOG_ALL = true;      // Записывать все действия или только ошибки
```
### Настроечные константы ORACLE
```php
const DB_ORACLE_HOST = 'db.example'; // Имя/адрес сервера БД
const DB_ORACLE_PORT = 1521;        // Порт сервера Oracle
const DB_ORACLE_NAME = 'service';   // Имя базы данных
const DB_ORACLE_USER = 'user';      // Имя пользователя
const DB_ORACLE_PASS = 'password';  // Пароль пользователя
const DB_ORACLE_STORAGE = true;     // Сохранять подключение на весь сеанс
const DB_ORACLE_CHARSET = 'AL32UTF8'; // Кодировка
const DB_ORACLE_DEBUG = false;      // Включить или отключить отладочные функции
const DB_ORACLE_ERROR_EXIT = false; // Выбрасывать DatabaseException при ошибке
const DB_ORACLE_LOG_NAME = 'db.log'; // Имя файла логов
const DB_ORACLE_LOG_ALL = true;     // Записывать все действия или только ошибки
const DB_ORACLE_USE_HOST = 2;       // Тип записи для подключения к Oracle:
                                    //  0 - используется только имя базы данных
                                    //  1 - используется хост и имя базы данных
                                    //  2 - используется полная запись для подключения
```
### Настроечные константы PDOLIB
```php
const DB_PDO_TYPE = 'mysql';        // Тип БД ['mysql', 'pgsql', 'oci', 'odbc', 'sqlite']
const DB_PDO_HOST = '127.0.0.1';    // Имя/адрес сервера БД
const DB_PDO_PORT = 3306;           // Порт сервера
const DB_PDO_NAME = 'database';     // Имя базы данных
const DB_PDO_USER = 'user';          // Имя пользователя
const DB_PDO_PASS = 'password';      // Пароль пользователя
const DB_PDO_DEBUG = false;          // Включить или отключить отладочные функции
const DB_PDO_ERROR_EXIT = false;     // Выбрасывать DatabaseException при ошибке
const DB_PDO_ORACLE_CONNECT_TYPE = 2; // Тип записи для подключения к Oracle:
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
$MYSQL = new Toropyga\DB\MySQL();
$POSTGRESQL = new Toropyga\DB\PostgreSQL();
$ORACLE = new Toropyga\DB\Oracle();
$PDO = new Toropyga\DB\PDOLIB();
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
$MYSQL = new Toropyga\DB\MySQL($HOST, $PORT, $NAME, $USER, $PASS);

/**
 * Конструктор PostgreSQL.
 * @param string $HOST - хост
 * @param int|string $PORT - порт
 * @param string $NAME - имя базы данных
 * @param string $USER - пользователь
 * @param string $PASS - пароль
 */
$POSTGRESQL = new Toropyga\DB\PostgreSQL($HOST, $PORT, $NAME, $USER, $PASS);

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
$ORACLE = new Toropyga\DB\Oracle($HOST, $NAME, $USER, $PASS, $USE_HOST, $PORT, $P_CONNECT, $CHARSET, $no_connect);

/**
 * PDOLIB constructor.
 * @param string $db_type - тип БД ['mysql', 'pgsql', 'oci', 'odbc', 'sqlite']
 * @param string $NAME - имя базы данных
 * @param string $USER - пользователь
 * @param string $PASS - пароль
 * @param string $HOST - сервер
 * @param string $PORT - порт
 * @param string $oracle_connect_type - Тип используемой записи для подключения к Oracle:
 *      0 - используется только имя базы данных
 *      1 - используется хост и имя базы данных
 *      2 - используется полная запись для подключения
 */
$PDO = new Toropyga\DB\PDOLIB($db_type, $NAME, $USER, $PASS, $HOST, $PORT, $oracle_connect_type);
```
---
### Получение списка таблиц
```php
$tables1 = $MYSQL->getTableList();
$tablesPostgreSQL = $POSTGRESQL->getTableList();
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
### Отправка запроса
```php
$result1 = $MYSQL->getResults($sql, $one);
$result2 = $ORACLE->getResults($sql, $one);
$result3 = $PDO->getResults($sql, $one);
```

Для параметризованных SELECT-запросов используйте `getQuery()`, а не
конкатенацию пользовательских значений со строкой SQL. Адаптер применяет
настоящие связанные параметры:

```php
$users = $PDO->getQuery(
    'SELECT id, name FROM users WHERE status = :status',
    ['status' => 'active'],
    'all'
);
```

Методы `getInsertSQL()`, `getUpdateSQL()`, `getDeleteSQL()` и `getQuerySQL()`
формируют текст SQL для просмотра или журналирования. Для пользовательских
значений предпочтительны методы выполнения со связанными параметрами:
`getQuery()`, `prepare()` и `execute()`.
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
* 7 - возврат данных о плане выполнения запроса (EXPLAIN)

Строковые (аналог числовых):
* 'all' или '' - (выборка: любое количество строк и столбцов) ожидаем массив ассоциативных массивов ([] => array(имя_поля => значение));
* 'one' - (выборка: одна строка / один столбец) ожидаем строку, если при выборке получилось более одного столбца - возвращает ассоциативный массив (имя_поля => значение), если более одной строки - возвращает массив значений ([] => значение), если более одной строки и более одного столбца - массив ассоциативных массивов ([] => array(имя_поля => значение));
* 'row' - (выборка: одна строка / множество столбцов) ожидаем ассоциативный массив (имя_поля => значение), если более одной строки и один столбец - возвращает массив значений ([] => значение), если более одной строки и более одного столбца - массив ассоциативных массивов ([] => array(имя_поля => значение));
* 'column' - (выборка: множество строк / один столбец) ожидаем ассоциативный массив массивов (имя_поля => array([] => значение), если более одной строки и более одного столбца - массив ассоциативных массивов ([] => array(имя_поля => значение));
* 'col' - (выборка: множество строк / один столбец) ожидаем массив значений ([] => значение), если более одной строки и более одного столбца - массив ассоциативных массивов ([] => array(имя_поля => значение)).
* 'dub' - (выборка: множество строк / 2 столбца) ожидаем массив значений ([значение поля 1] => значение поля 2)
* 'dub_all' - (выборка: множество строк / 2 столбца) ожидаем массив значений ([значение поля 1] => значение поля 2), если [значение поля 1] повторяется, то массив принимает вид [значение поля 1] => array([0] => значение поля 2, [1] => значение поля 2...)
* 'explain' - возврат данных о плане выполнения запроса (EXPLAIN)
```

Режимы `6`/`'dub_all'` и `7`/`'explain'` поддерживаются во всех трёх классах, с одним исключением:

* У **`Oracle`** нет однострочного `EXPLAIN`. Режим `7`/`'explain'` под капотом
  выполняет `EXPLAIN PLAN FOR <sql>`, а затем `SELECT ... FROM TABLE(DBMS_XPLAN.DISPLAY())`,
  и возвращает отформатированный план в виде плоского массива текстовых строк
  (а не структурированный по строкам результат, как остальные числовые режимы).
* **`PDOLIB`** ведёт себя так же для типа драйвера `oci`. Для `mysql` и `pgsql`
  запрос просто выполняется с префиксом `EXPLAIN `. Для типа драйвера `odbc`
  режим `'explain'` **не поддерживается** — единого синтаксиса `EXPLAIN`,
  переносимого между разными ODBC-бэкендами, не существует — при вызове в лог
  записывается сообщение и возвращается пустой массив вместо отправки
  непредсказуемого SQL в базу данных.

А также можно выполнить запрос без обработки результата (UPDATE, INSERT и т.д.):
```php
$MYSQL->query($sql);
```

Для Oracle обычные SELECT-запросы выполняются через разобранный statement.
Вызывайте `setCursor(true)` только для PL/SQL-запросов с OUT-cursor, например
с параметром `:res`; для обычного SELECT режим cursor не требуется.

`getListFields()` возвращает список имен столбцов. При ошибке получения
метаданных возвращается `false`, а старый prepared statement или подключение
не переиспользуются.

Для условий используйте значения `NULL` и `NOT NULL`, чтобы получить
`IS NULL` и `IS NOT NULL`, например: `['deleted_at' => 'NULL']`.