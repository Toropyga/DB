<?php
/**
 * Класс для работы с БД с использованием библиотеки PDO
 * !!! В процессе разработки !!!
 * @author FYN
 * Date: 16/09/2019
 * @version 0.1.2
 * @copyright 2019-2021
 */

namespace FYN\DB;

use PDO, PDOException;

class PDO_LIB extends AbstractDB {
    /**
     * Тип базы данных к которой подключаемся
     * @var string
     */
    private $db_type = 'mysql';
    /**
     * Массив поддерживаемых типов баз данных
     * @var array
     */
    private $db_types = ['mysql', 'pgsql', 'oci', 'odbc']; // todo sqlite,
    /**
     * Имя/адрес сервера БД
     * @var string
     */
    private $db_host; //Host name
    /**
     * Порт сервера
     * @var integer
     */
    private $db_port; //Port number
    /**
     * Имя базы данных
     * @var string
     */
    private $db_name; //Database name
    /**
     * Имя пользователя
     * @var string
     */
    private $db_user; //User name
    /**
     * Пароль пользователя
     * @var string
     */
    private $db_pass; //User password
    /**
     * Кодировка базы данных
     * @var string
     */
    private $db_charset = 'utf8';
    /**
     * Тип используемой записи для подключения к Oracle
     *      0 - используется только имя базы данных
     *      1 - используется хост и имя базы данных
     *      2 - используется полная запись для подключения
     * @var int
     */
    private $oracle_connect_type = 0;
    /**
     * Подключение к БД
     * @var object
     */
    private $db_connect;
    /**
     * Список полей в таблицах БД
     * @var array
     */
    private $db_TableList = array();
    /**
     * Список таблиц в БД
     * @var array
     */
    private $db_Tables = array();
    /**
     * Статус подключения к БД
     * @var bool
     */
    public $status = false;
    /**
     * Служебная переменная для взаимодействия с PDO
     * @var string
     */
    private $pdo = '';

    public function __construct($db_type = false, $NAME = false, $USER = false, $PASS = false, $HOST = false, $PORT = false, $oracle_connect_type = false) {
        if (defined('DB_PDO_TYPE') && !$db_type && in_array(DB_PDO_TYPE, $this->db_types)) $this->db_host = DB_PDO_TYPE;
        elseif ($db_type && in_array($db_type, $this->db_types)) $this->db_type = $db_type;
        if (defined('DB_PDO_HOST') && !$HOST) $this->db_host = DB_PDO_HOST; elseif ($HOST) $this->db_host = $HOST;
        if (defined('DB_PDO_PORT') && !$PORT) $this->db_port = DB_PDO_PORT; elseif ($PORT) $this->db_port = $PORT;
        if (defined('DB_PDO_NAME') && !$NAME) $this->db_name = DB_PDO_NAME; elseif ($NAME) $this->db_name = $NAME;
        if (defined('DB_PDO_USER') && !$USER) $this->db_user = DB_PDO_USER; elseif ($USER) $this->db_user = $USER;
        if (defined('DB_PDO_PASS') && !$PASS) $this->db_pass = DB_PDO_PASS; elseif ($PASS) $this->db_pass = $PASS;
        if (defined('DB_PDO_DEBUG')) $this->debug = DB_PDO_DEBUG;
        if (defined('DB_PDO_ERROR_EXIT')) $this->error_exit = DB_PDO_ERROR_EXIT;
        if (defined("DB_PDO_ORACLE_CONNECT_TYPE")) $oracle_connect_type = DB_PDO_ORACLE_CONNECT_TYPE;
        if (isset($oracle_connect_type) && $oracle_connect_type >= 0 && $oracle_connect_type <= 2) $this->setOracleConnectType($oracle_connect_type);
        $this->getConnect();
    }

    /**
     * Запись в лог
     * Деструктор класса.
     */
    public function __destruct() {
        $this->status = false;
    }

    /**
     * Основная функция для запросов на выборку
     * Set SQL query to DataBase and return query Result
     *
     * @param string $sql - SQL query to DataBase
     * @param int|string $one - return result parameter
     * Принимает значения:
     *  числовые:
     *      0 или '' - (выборка: любое количество строк и столбцов) ожидаем массив ассоциативных массивов ([] => array(имя_поля => значение));
     *      1 - (выборка: одна строка / один столбец) ожидаем строку, если при выборке получилось более одного столбца - возвращает ассоциативный массив (имя_поля => значение), если более одной строки - возвращает массив значений ([] => значение), если более одной строки и более одного столбца - массив ассоциативных массивов ([] => array(имя_поля => значение));
     *      2 - (выборка: одна строка / множество столбцов) ожидаем ассоциативный массив (имя_поля => значение), если более одной строки и один столбец - возвращает массив значений ([] => значение), если более одной строки и более одного столбца - массив ассоциативных массивов ([] => array(имя_поля => значение));
     *      3 - (выборка: множество строк / один столбец) ожидаем ассоциативный массив массивов (имя_поля => array([] => значение), если более одной строки и более одного столбца - массив ассоциативных массивов ([] => array(имя_поля => значение));
     *      4 - (выборка: множество строк / один столбец) ожидаем массив значений ([] => значение), если более одной строки и более одного столбца - массив ассоциативных массивов ([] => array(имя_поля => значение)).
     *      5 - (выборка: множество строк / 2 столбца) ожидаем массив значений ([значение поля 1] => значение поля 2)
     *      6 - (выборка: множество строк / 2 столбца) ожидаем массив значений ([значение поля 1] => значение поля 2), если [значение поля 1] повторяется, то массив принимает вид [значение поля 1] => array([0] => значение поля 2, [1] => значение поля 2...)
     *  строковые (аналог числовых):
     *      'all' или '' - (выборка: любое количество строк и столбцов) ожидаем массив ассоциативных массивов ([] => array(имя_поля => значение));
     *      'one' - (выборка: одна строка / один столбец) ожидаем строку, если при выборке получилось более одного столбца - возвращает ассоциативный массив (имя_поля => значение), если более одной строки - возвращает массив значений ([] => значение), если более одной строки и более одного столбца - массив ассоциативных массивов ([] => array(имя_поля => значение));
     *      'row' - (выборка: одна строка / множество столбцов) ожидаем ассоциативный массив (имя_поля => значение), если более одной строки и один столбец - возвращает массив значений ([] => значение), если более одной строки и более одного столбца - массив ассоциативных массивов ([] => array(имя_поля => значение));
     *      'column' - (выборка: множество строк / один столбец) ожидаем ассоциативный массив массивов (имя_поля => array([] => значение), если более одной строки и более одного столбца - массив ассоциативных массивов ([] => array(имя_поля => значение));
     *      'col' - (выборка: множество строк / один столбец) ожидаем массив значений ([] => значение), если более одной строки и более одного столбца - массив ассоциативных массивов ([] => array(имя_поля => значение)).
     *      'dub' - (выборка: множество строк / 2 столбца) ожидаем массив значений ([значение поля 1] => значение поля 2)
     *      'dub_all' - (выборка: множество строк / 2 столбца) ожидаем массив значений ([значение поля 1] => значение поля 2), если [значение поля 1] повторяется, то массив принимает вид [значение поля 1] => array([0] => значение поля 2, [1] => значение поля 2...)
     *
     * @return mixed SQL query result
     */
    public function getResults ($sql, $one=0) { // Get query results
        $this->query($sql);
        if (is_string($one)) {
            if ($one == 'all') $one = 0;
            elseif ($one == 'one') $one = 1;
            elseif ($one == 'row') $one = 2;
            elseif ($one == 'column') $one = 3;
            elseif ($one == 'col') $one = 4;
            elseif ($one == 'dub') $one = 5;
            elseif ($one == 'dub_all') $one = 6;
            else {
                $this->logs[] = "Wrong parameter ONE: ".$one;
                $one = 0;
            }
        }
        if ($one > 5 || $one < 0) {
            $this->logs[] = "Wrong parameter ONE: ".$one;
            $one = 0;
        }
        $result = array();
        if (is_object($this->pdo) && method_exists($this->pdo, 'columnCount')) {
            $col_count = $this->pdo->columnCount();
            if (!$col_count && $one != 1) return array();
            elseif (!$col_count && $one == 1) $result = '';
            else $result = $this->res2array($one);
        }
        return $result;
    }

    /**
     * Обработка результата и формирование массива полученных данных
     * @param int $one - параметр обработки (см. getResults)
     * @return array
     */
    private function res2array ($one = 0) { // Set query results to array
        $result = array();
        if (is_object($this->pdo) && method_exists($this->pdo, 'columnCount') && method_exists($this->pdo, 'rowCount')) {
            $col_count = $this->pdo->columnCount();
            $row_count = $this->pdo->rowCount();
        }
        else $col_count = $row_count = 0;
        if ($col_count == 1 && $row_count == 1 && $one == 1) {
            $res = $this->fetch(PDO::FETCH_NUM);
            $result = $res[0];
        }
        elseif ($row_count == 1 && $one == 2) $result = $this->fetch(PDO::FETCH_ASSOC);
        elseif ($col_count && $one >= 3 && $one <= 5) {
            while ($row = $this->fetch(PDO::FETCH_ASSOC)) {
                if ($one && (is_array($row) || is_object($row)) && sizeof($row) == 1) {
                    foreach ($row as $key => $value) {
                        if ($one == 3) $result[$key][] = $value;
                        else $result[] = $value;
                    }
                } elseif ($one == 5 && sizeof($row) == 2) {
                    $idx = 0;
                    $index = '';
                    $value = '';
                    foreach ($row as $rvalue) {
                        if ($idx == 0) $index = $rvalue;
                        else $value = $rvalue;
                        $idx++;
                    }
                    if (!$index) $index = 'no_value_' . $idx;
                    $result[$index] = $value;
                } else $result[] = $row;
            }
        }
        elseif ($col_count == 2 && $one == 6) {
            //альтернативный вариант обработки данных (выборка: множество строк / 2 столбца) с учётом повторяющихся ключей и значений
            $keys = array();
            $idx = 0;
            while ($row = $this->fetch(PDO::FETCH_NUM)) {
                $key = $row[0];
                if (!$key) $key = 'no_value_'.$idx;
                $val = $row[1];
                if (!in_array($key, $keys)) {
                    $keys[] = $key;
                    $result[$key] = $val;
                }
                else {
                    if (is_array($result[$key])) {
                        if (!in_array($val, $result[$key])) $result[$key][] = $val;
                    }
                    else {
                        $value = $result[$key];
                        if ($value != $val) {
                            $result[$key] = array();
                            $result[$key][] = $value;
                            $result[$key][] = $val;
                        }
                    }
                }
                $idx++;
            }
        }
        else $result = $this->fetchAll(PDO::FETCH_ASSOC);
        return $result;
    }

    /**
     * Установка типа используемой записи для подключения к Oracle
     *      0 - используется только имя базы данных
     *      1 - используется хост и имя базы данных
     *      2 - используется полная запись для подключения
     * @param int $type
     */
    private function setOracleConnectType ($type = 0) {
        if ($type != 1 || $type != 2) $this->oracle_connect_type = 0;
        else $this->oracle_connect_type = $type;
    }

    /**
     * Инициация подключения к базе данных
     * @return boolean
     * @throws PDOException
     */
    private function getConnect() {
        $code = 'getConnect';
        switch ($this->db_type) {
            case 'oci':
                if (!$this->db_port) $this->db_port = '1521';
                if ($this->oracle_connect_type == 1) $db = $this->db_host."/".$this->db_name;
                elseif ($this->oracle_connect_type == 2) $db = "(DESCRIPTION=(ADDRESS_LIST = (ADDRESS = (PROTOCOL = TCP)(HOST = ".$this->db_host.")(PORT = ".$this->db_port.")))(CONNECT_DATA=(SERVER=DEDICATED)(SERVICE_NAME=".$this->db_name.")))";
                else $db = $this->db_name;
                $connect_line = "oci:dbname=".$db;
                break;
            case 'pgsql':
                if (!$this->db_port) $this->db_port = 5432;
                $connect_line = "pgsql:host=".$this->db_host.";port=".$this->db_port.";dbname=".$this->db_name;//.";charset=".$this->db_charset;
                break;
            case 'odbc':
                $connect_line = "odbc:".$this->db_name;
                break;
            case 'mysql':
            default:
                if (!$this->db_port) $this->db_port = 3306;
                $connect_line = "mysql:host=".$this->db_host.";port=".$this->db_port.";dbname=".$this->db_name.";charset=".$this->db_charset;
        }
        $opt = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_ERRMODE => PDO::ERRMODE_WARNING,
        ];
        try {
            $this->db_connect = new PDO($connect_line, $this->db_user, $this->db_pass, $opt);
            if (isset($this->error_code[$code]) && $this->error_code[$code]) unset($this->error_code[$code]);
        }
        catch (PDOException $e) {
            return $this->DB_Error("Could not connect to host: $this->db_host.\n Port: $this->db_port.\nError: ".$e->getMessage(), $code);
        }
        $this->status = true;
        return true;
    }

    /**
     * Подготовка запроса к базе данных по правилам модуля PDO
     * @param $sql - запрос
     * @param array $values - передаваемые параметры
     * @return string
     */
    public function prepare ($sql, $values = array(PDO::ATTR_CURSOR => PDO::CURSOR_FWDONLY)) {
        $code = 'prepare';
        try {
            $this->pdo = $this->db_connect->prepare($sql, $values);
            if (isset($this->error_code[$code]) && $this->error_code[$code]) unset($this->error_code[$code]);
        }
        catch (PDOException $e) {
            $message = "Could not prepare: $sql\nError: ".$e->getMessage();;
            return $this->DB_Error($message, $code);
        }
        return $this->pdo;
    }

    /**
     * Выполнение ранее подготовленного запроса
     * @param $values - значения, подставляемые в подготовленный запрос
     * @param mixed $pdo - объект модуля PDO из функции prepare
     * @return bool
     */
    public function execute ($values, $pdo = '') {
        $code = 'execute';
        if (is_object($pdo) && method_exists($pdo, 'execute')) {
            try {
                $pdo->execute($values);
                if (isset($this->error_code[$code]) && $this->error_code[$code]) unset($this->error_code[$code]);
            }
            catch (PDOException $e) {
                $message = "Could not execute.\nError: ".$e->getMessage();;
                return $this->DB_Error($message, $code);
            }
        }
        elseif (is_object($this->pdo) && method_exists($this->pdo, 'execute')) {
            try {
                $this->pdo->execute($values);
                if (isset($this->error_code[$code]) && $this->error_code[$code]) unset($this->error_code[$code]);
            }
            catch (PDOException $e) {
                $message = "Could not execute.\nError: ".$e->getMessage();;
                return $this->DB_Error($message, $code);
            }
        }
        return false;
    }

    /**
     * Построковая выборка данных
     * @param string $mode - параметры выборки
     * @param mixed $pdo - объект модуля PDO из функции prepare или query
     * @return bool
     */
    public function fetch ($mode = '', $pdo = '') {
        if (is_object($pdo) && method_exists($pdo, 'fetch')) return $pdo->fetch($mode);
        elseif (is_object($this->pdo) && method_exists($this->pdo, 'fetch')) return $this->pdo->fetch($mode);
        return false;
    }

    /**
     * Выборка массива всех данных
     * @param string $mode - параметры выборки
     * @param mixed $pdo - объект модуля PDO из функции prepare или query
     * @return bool
     */
    public function fetchAll ($mode = '', $pdo = '') {
        if (is_object($pdo) && method_exists($pdo, 'fetchAll')) return $pdo->fetchAll($mode);
        elseif (is_object($this->pdo) && method_exists($this->pdo, 'fetchAll')) return $this->pdo->fetchAll($mode);
        return false;
    }

    /**
     * Выполнение SQL запроса к Базе данных
     * @param $sql - запрос
     * @return string
     */
    public function query ($sql) {
        $code = 'query';
        try {
            $this->pdo = $this->db_connect->query($sql);
            if (isset($this->error_code[$code]) && $this->error_code[$code]) unset($this->error_code[$code]);
        }
        catch (PDOException $e) {
            $message = "Could not query: $sql\nError: ".$e->getMessage();;
            return $this->DB_Error($message, $code);
        }
        return $this->pdo;
    }

    /**
     * Установка параметров выборки
     * @param int $mode - параметры выборки
     * @param mixed $param_1 - первая группа дополнительных параметров
     * @param mixed $param_2 - вторая группа дополнительных параметров
     * @param mixed $pdo - объект модуля PDO из функции prepare или query
     * @return bool
     */
    public function setFetchMode ($mode = PDO::FETCH_ASSOC, $param_1 = '', $param_2 = '', $pdo = '') {
        if (is_object($pdo) && method_exists($pdo, 'setFetchMode')) return $pdo->setFetchMode($mode, $param_1, $param_2);
        if (is_object($this->pdo) && method_exists($this->pdo, 'setFetchMode')) return $this->pdo->setFetchMode($mode, $param_1, $param_2);
        return false;
    }

    /**
     * Получение списка таблиц в БД
     * @return array|mixed
     */
    public function getTableList () {
        switch ($this->db_type) {
            case 'oci':
                $sql = "SELECT table_name FROM user_tables";
                $this->db_Tables = $this->getResults($sql, 4);
                break;
            case 'pgsql':
                $sql = "SELECT table_name FROM information_schema.tables WHERE table_schema='public' AND table_type='BASE TABLE' AND table_catalog='".$this->db_name."'";
                $Tables = $this->getResults($sql, 4);
                foreach ($Tables as $i=>$tableName) $this->db_Tables[$i] = $tableName;
                break;
            case 'odbc':
                $sql = "SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_TYPE = 'BASE TABLE' AND TABLE_CATALOG='".$this->db_name."'";
                $Tables = $this->getResults($sql, 4);
                foreach ($Tables as $i=>$tableName) $this->db_Tables[$i] = $tableName;
                break;
            case 'mysql':
            default:
                $sql = "SHOW TABLES FROM ".$this->db_name;
                $this->db_Tables = $this->getResults($sql, 4);
        }
        return $this->db_Tables;
    }

    /**
     * Получение списка существующих полей в таблице
     * @param $table - имя таблицы
     * @return array|mixed
     */
    public function getListFields($table) { // Get Fields from table
        $code = 'getListFields';
        $name_field = array();
        if (!count($this->db_Tables)) $this->getTableList();
        if (!in_array($table, $this->db_Tables)) {
            $this->DB_Error("Could not create List Fields: Table - $table not exists", $code);
            return false;
        }
        if (!isset($this->db_TableList[$table])) {
            switch ($this->db_type) {
                case 'odbc':
                case 'pgsql':
                    $sql = "SELECT column_name FROM information_schema.columns WHERE table_name =  '$table'"; // pgsql
                    break;
                case 'oci':
                    $sql = "SELECT column_name FROM user_tab_cols WHERE table_name = '$table'"; // oracle
                    break;
                case 'mysql':
                default:
                    $sql = "SHOW COLUMNS FROM $table"; // mysql
            }
            $fields = $this->getResults($sql, 4);
            foreach ($fields as $key=>$value) $name_field[] = $value['Field'];
            $this->db_TableList[$table]=$name_field;
        }
        else {
            reset($this->db_TableList[$table]);
            $name_field = $this->db_TableList[$table];
        }
        return $name_field;
    }

    /**
     * Создание одиночного Insert запроса
     * @param string $table - имя таблицы
     * @param array $values - массив данных для добавления в формате array(['имя_поля'] => 'значение');
     * @return string
     */
    public function getInsertSQL ($table, $values) { // Create Insert query
        $code = 'getInsertSQL';
        if (!$tab_fields = $this->getListFields($table)) return FALSE;
        if (!is_array($values)) {
            $this->DB_Error("Could not create update query: Error values - $values (not array)", $code);
            return false;
        }
        elseif (isset($this->error_code[$code]) && $this->error_code[$code]) unset($this->error_code[$code]);
        $fields = '';
        $val = '';
        foreach ($values as $key => $value) {
            if (in_array($key,$tab_fields)) {
                $fields = ($fields)?"$fields, `$key`":"`$key`";
                if (is_array($value)) $value = json_encode($value);
                else $value = addslashes($value);
                $value = ($value == 'NULL')?$value:"'$value'";
                $val = ($val)?"$val, $value":"$value";
            }
        }
        $sql = "INSERT INTO $table ($fields) VALUES ($val)";
        return $sql;
    }

    /**
     * Создание Update запроса
     * @param string $table - имя таблицы
     * @param array $values - массив данных для обновления в формате array(['имя_поля'] => 'значение');
     * @param mixed $index - массив данных условия WHERE в формате array(['имя_поля'] => 'значение');
     * @return string
     */
    public function getUpdateSQL ($table, $values, $index=false) { // Create Update query
        $code = 'getUpdateSQL';
        if (!$tab_fields = $this->getListFields($table)) return false;
        if (!is_array($values)) {
            $this->DB_Error("Could not create update query: Error values - $values (not array)", $code);
            return false;
        }
        elseif (isset($this->error_code[$code]) && $this->error_code[$code]) unset($this->error_code[$code]);
        $fields = '';
        foreach ($values as $key => $value) {
            if (in_array($key,$tab_fields)) {
                if (is_array($value)) $value = json_encode($value);
                else $value = addslashes($value);
                $value = ($value == 'NULL')?$value:"'$value'";
                $fields = ($fields)?"$fields, `$key` = $value":"`$key` = $value";
            }
        }
        $ind = '';
        if ($index) {
            if (!is_array($index)) {
                $this->DB_Error("Could not create update query: Error keys - $index (not array)", $code);
                return false;
            }
            elseif (isset($this->error_code[$code]) && $this->error_code[$code]) unset($this->error_code[$code]);
            foreach ($index as $key => $value) {
                if (in_array($key,$tab_fields)) {
                    if ($value == 'NULL') $ind = ($ind)?"$ind AND `$key` IS NULL":"`$key` IS NULL";
                    elseif ($value == 'NOT NULL') $ind = ($ind)?"$ind AND `$key` IS NOT NULL":"`$key` IS NOT NULL";
                    else $ind = ($ind)?"$ind AND `$key` = '$value'":"`$key` = '$value'";
                }
            }
        }
        if ($ind) $ind = "WHERE $ind";
        $sql = "UPDATE $table SET $fields $ind";
        return $sql;
    }

    /**
     * Создание Delete запроса
     * @param string $table - имя таблицы
     * @param mixed $index - массив данных условия WHERE в формате array(['имя_поля'] => 'значение');
     * @return string
     */
    public function getDeleteSQL ($table, $index=false) { // Create Delete query
        $code = 'getDeleteSQL';
        if (!$tab_fields = $this->getListFields($table)) return false;
        $ind = '';
        if ($index) {
            if (!is_array($index)) {
                $this->DB_Error("Could not create delete query: Error keys - $index (not array)");
                return FALSE;
            }
            elseif (isset($this->error_code[$code]) && $this->error_code[$code]) unset($this->error_code[$code]);
            foreach ($index as $key => $value) {
                $ind = '';
                if (in_array($key,$tab_fields)) {
                    if ($value == 'NULL') $ind = ($ind)?"$ind AND `$key` IS NULL":"`$key` IS NULL";
                    elseif ($value == 'NOT NULL') $ind = ($ind)?"$ind AND `$key` IS NOT NULL":"`$key` IS NOT NULL";
                    else $ind = ($ind)?"$ind AND `$key` = '$value'":"`$key` = '$value'";
                }
            }
        }
        if ($ind) $ind = "WHERE $ind";
        $sql = "DELETE FROM $table $ind";
        return $sql;
    }

    /**
     * Возвращает ID последней добавленной записи в таблицу
     * @param string $name - имя таблицы или имя объекта последовательности, который должен выдать ID (pgsql), если не указано, то возвращает последний ID во всей БД
     * @return mixed
     */
    public function lastID ($name = '') {
        if ($this->db_type == 'mysql') {
            if (!$name) return $this->getResults("SELECT LAST_INSERT_ID()", 1);
            else return $this->getResults("SELECT LAST_INSERT_ID() FROM $name", 1);
        }
        else return $this->db_connect->lastInsertId($name);
    }

    /**
     * Обработка ошибок.
     * Вывод на экран, отправка на почту администратору, сохранение в переменную error.
     * @param bool $message - сообщение об ошибке
     * @param string $code - код ошибки
     * @param mixed $pdo - объект подключения
     * @return bool
     */
    private function DB_Error ($message=false, $code = '', $pdo = '') {
        if ($code && isset($this->error_code[$code])) return false;
        elseif ($code) $this->error_code[$code] = true;
        $message_bd = '';
        if (is_object($this->db_connect)) {
            if (method_exists($this->db_connect, 'errorInfo')) $message_bd = htmlentities($this->db_connect->errorInfo());
        }
        if (!$message_bd && is_object($pdo) && method_exists($pdo, 'errorInfo')) $message_bd = $pdo->errorInfo();
        elseif (!$message_bd && method_exists($this->pdo, 'errorInfo')) $message_bd = $this->pdo->errorInfo();
        if (is_array($message_bd)) $message_bd = $message_bd[2];
        list($mess) = preg_split("/:/", $message);
        $query = htmlentities(trim(strtr($message, array($mess.":"=>''))));
        $message = $mess;
        if ($query && strlen(trim($query))) $message .= ". QUERY: ".$query;
        $message .= " ERROR: ".$message_bd;
        parent::Error($message, 'PDO_LIB');
        if ($this->error_exit) exit;
        return false;
    }
}