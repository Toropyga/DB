<?php

/**
 * Класс для работы с БД MySQL
 * @author FYN
 * Date: 15/04/2005
 * @version 5.0.3
 * @copyright 2005-2021
 */

namespace FYN\DB;

use mysqli_result;

class MySQL extends AbstractDB {

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
     * Записывать в лог все действия (true) или только ошибки (false)
     * @var bool
     */
    private $log_all = true;
    /**
     * Подключение к БД
     * @var object
     */
    private $db_connect = [];
    /**
     * Статус подключения к БД
     * @var bool
     */
    public $status = false;
    /**
     * Сохранять подключение на весь сеанс или подключаться при каждом SQL-запросе
     * @var bool
     */
    private $db_storage = true;
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
     * Использовать ли постоянное подключение
     * @var bool
     */
    private $use_transaction = true;

    /**
     * DBMySQL constructor.
     * Класс для работы с БД MySQL
     * @param mixed $HOST - хост
     * @param mixed $PORT - порт
     * @param mixed $NAME - имя БД
     * @param mixed $USER - пользователь
     * @param mixed $PASS - пароль
     */
    public function __construct ($HOST = false, $PORT = false, $NAME = false, $USER = false, $PASS = false) {
        if (defined('DB_MYSQL_HOST') && !$HOST) $this->db_host = DB_MYSQL_HOST; elseif ($HOST) $this->db_host = $HOST;
        if (defined('DB_MYSQL_PORT') && !$PORT) $this->db_port = DB_MYSQL_PORT; elseif ($PORT) $this->db_port = $PORT;
        if (defined('DB_MYSQL_NAME') && !$NAME) $this->db_name = DB_MYSQL_NAME; elseif ($NAME) $this->db_name = $NAME;
        if (defined('DB_MYSQL_USER') && !$USER) $this->db_user = DB_MYSQL_USER; elseif ($USER) $this->db_user = $USER;
        if (defined('DB_MYSQL_PASS') && !$PASS) $this->db_pass = DB_MYSQL_PASS; elseif ($PASS) $this->db_pass = $PASS;
        if (defined('DB_MYSQL_STORAGE')) $this->db_storage = DB_MYSQL_STORAGE;
        if (defined('DB_MYSQL_USE_TRANSACTION')) $this->use_transaction = DB_MYSQL_USE_TRANSACTION;
        if (defined('DB_MYSQL_DEBUG')) $this->debug = DB_MYSQL_DEBUG;
        if (defined('DB_MYSQL_ERROR_EXIT')) $this->error_exit = DB_MYSQL_ERROR_EXIT;
        if (defined('DB_MYSQL_LOG_NAME')) $this->log_file = DB_MYSQL_LOG_NAME;
        if (defined('DB_MYSQL_LOG_ALL')) $this->log_all = DB_MYSQL_LOG_ALL;
        if (!function_exists('mysqli_connect')) {
            if ($this->log_all) $this->logs[] = "PHP MySQL not installed!";
            $this->DB_Error("PHP MySQL not installed!", '__construct');
        }
        else if ($this->db_storage) $this->getConnect();
        return true;
    }


    /**
     * Деструктор класса.
     */
    public function __destruct() {
        $this->getClose();
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
     *      7 - возврат данных по выполнению запроса (EXPLAIN)
     *  строковые (аналог числовых):
     *      'all' или '' - (выборка: любое количество строк и столбцов) ожидаем массив ассоциативных массивов ([] => array(имя_поля => значение));
     *      'one' - (выборка: одна строка / один столбец) ожидаем строку, если при выборке получилось более одного столбца - возвращает ассоциативный массив (имя_поля => значение), если более одной строки - возвращает массив значений ([] => значение), если более одной строки и более одного столбца - массив ассоциативных массивов ([] => array(имя_поля => значение));
     *      'row' - (выборка: одна строка / множество столбцов) ожидаем ассоциативный массив (имя_поля => значение), если более одной строки и один столбец - возвращает массив значений ([] => значение), если более одной строки и более одного столбца - массив ассоциативных массивов ([] => array(имя_поля => значение));
     *      'column' - (выборка: множество строк / один столбец) ожидаем ассоциативный массив массивов (имя_поля => array([] => значение), если более одной строки и более одного столбца - массив ассоциативных массивов ([] => array(имя_поля => значение));
     *      'col' - (выборка: множество строк / один столбец) ожидаем массив значений ([] => значение), если более одной строки и более одного столбца - массив ассоциативных массивов ([] => array(имя_поля => значение)).
     *      'dub' - (выборка: множество строк / 2 столбца) ожидаем массив значений ([значение поля 1] => значение поля 2)
     *      'explain' - возврат данных по выполнению запроса (EXPLAIN)
     *
     * @return array|bool|mysqli_result|string|string[]|null SQL query result
     */
    public function getResults ($sql, $one=0) { // Set query results
        $one = parent::checkReturnType($one);
        if ($one === false) {
            $this->logs[] = "Wrong parameter ONE: ".$one;
            $one = 0;
        }
        if ($one == 7) {
            $sql = 'EXPLAIN '.$sql;
            $one = 2;
        }
        $res = $this->query($sql, 1);
        if (!is_string($res) && is_object($res)) {
            $col_row = mysqli_num_rows($res);
            if (!$col_row && $one != 1) return array();
            elseif (!$col_row && $one == 1) $result = '';
            elseif ($col_row == 1 && $one && $one < 3) {
                $result = mysqli_fetch_assoc($res);
                if (sizeof($result) == 1 && $one == 1) $result = join('',$result);
            }
            else $result = $this->res2array($res, $one);
        }
        else $result = $res;
        if (!$this->db_storage) $this->getClose();
        return $result;
    }

    /**
     * Подключение к БД MySQL
     * @return bool
     */
    private function getConnect () { // Connect to database
        $code = 'getConnect';
        if ($this->use_transaction) {
            if ($this->db_port) {
                if (!$this->db_connect = @mysqli_connect($this->db_host, $this->db_user, $this->db_pass, $this->db_name, $this->db_port)) return $this->DB_Error("Could not connect to host: $this->db_host.\n Port: $this->db_port.\n Info: ".mysqli_connect_error(), $code);
                elseif (isset($this->error_code[$code]) && $this->error_code[$code]) unset($this->error_code[$code]);
            }
            else {
                if (!$this->db_connect = @mysqli_connect($this->db_host, $this->db_user, $this->db_pass)) return $this->DB_Error("Could not connect to host: $this->db_host.\n Port: $this->db_port.\n Info: ".mysqli_connect_error(), $code);
                elseif (isset($this->error_code[$code]) && $this->error_code[$code]) unset($this->error_code[$code]);
            }
            @mysqli_autocommit($this->db_connect, TRUE);
        }
        else {
            if ($this->db_port) $host = $this->db_host.':'.$this->db_port;
            else $host = $this->db_host;
            if (!$this->db_connect = @mysqli_connect($host, $this->db_user, $this->db_pass)) return $this->DB_Error("Could not connect to host: $this->db_host.\n Port: $this->db_port.\n Info: ".mysqli_connect_error(), $code);
            elseif (isset($this->error_code[$code]) && $this->error_code[$code]) unset($this->error_code[$code]);
        }
        if ($this->log_all) $this->logs[] = "Connect to MySQL Host: ".$this->db_host.", User: ". $this->db_user.", DB: ".$this->db_name." - success";
        $this->setCharset();
        if (!count($this->db_Tables)) $this->getTableList();
        $this->status = true;
        return true;
    }

    /**
     * Закрытие подключения к MySQL
     * @return bool
     */
    public function getClose () {
        if ($this->db_connect) {
            @mysqli_close($this->db_connect);
            if ($this->log_all) $this->logs[] = "Disconnect from MySQL Host: " . $this->db_host . " - success";
            $this->status = false;
        }
        else {
            if ($this->status) $this->status = false;
            if ($this->log_all) $this->logs[] = "MySQL Host: " . $this->db_host . " - is already disconnected";
        }
        return true;
    }

    /**
     * Выполнение запроса к БД
     * @param string $sql - SQL запрос
     * @param integer $other_function - выполнение запроса из другой функции (не прямой запрос, закрываем соединение)
     * @return bool|mysqli_result
     */
    public function query ($sql, $other_function = 0) { // SQL query
        $code = 'query';
        $connected = true;
        if (!$this->db_storage) $connected = $this->getConnect();
        if (!$connected) return false;
        if (!$this->getDB()) return false;
        if (!is_string($sql) || !$sql || trim($sql) == '') return false;
        $run_time = time();
        $res = @mysqli_query($this->db_connect, $sql);
        $this->run_time = time() - $run_time;
        if ($res === false) {
            $message = "Could not query: $sql;";// Error message: ".mysqli_error($this->db_connect);
            $this->DB_Error($message, $code);
        }
        elseif (isset($this->error_code[$code]) && $this->error_code[$code]) unset($this->error_code[$code]);
        if (!$this->db_storage && !$other_function) $this->getClose();
        if ($this->log_all) $this->logs[] = "MySQL QUERY: ".$sql." - success";
        return $res;
    }

    /**
     * Обработка результата и формирование массива полученных данных
     * @param $res - объект с результатами
     * @param int $one - параметр обработки (см. getResults)
     * @return array
     */
    private function res2array ($res, $one = 0) { // Get query results to array
        $result = array();
        if (is_array($res)) return $res;
        if (is_resource($res) || $this->use_transaction) {
            if ($this->use_transaction && !mysqli_num_rows ($res)) return $result;
            elseif (!$this->use_transaction && !mysqli_num_rows ($res)) return $result;
            while ($row=mysqli_fetch_assoc ($res)) {
                if ($one && sizeof($row) == 1) {
                    foreach ($row as $key=>$value) {
                        if ($one == 3) $result[$key][] = $value;
                        else $result[] = $value;
                    }
                }
                elseif ($one == 5 && sizeof($row) == 2) {
                    $idx = 0;
                    $index = '';
                    $value = '';
                    foreach ($row as $rvalue) {
                        if ($idx == 0) $index = $rvalue;
                        else $value = $rvalue;
                        $idx++;
                    }
                    if (!$index) $index = 'no_value_'.$idx;
                    $result[$index] = $value;
                }
                else $result[]=$row;
            }
        }
        return $result;
    }

    /**
     * Выбор указанной базы данных
     * @return bool
     */
    private function getDB () { // Select DB
        $code = 'getDB';
        if ($this->use_transaction && @mysqli_select_db($this->db_connect, $this->db_name)) {
            if (isset($this->error_code[$code]) && $this->error_code[$code]) unset($this->error_code[$code]);
            return true;
        }
        elseif (@mysqli_select_db($this->db_connect, $this->db_name)) {
            if (isset($this->error_code[$code]) && $this->error_code[$code]) unset($this->error_code[$code]);
            return true;
        }
        else return $this->DB_Error("Could not select database: $this->db_name.", $code);
    }

    /**
     * Установка кодировки для базы данных
     * @param string $charset - используемая кодировка
     */
    public function setCharset ($charset = 'utf8') {
        $mysql_ver = @mysqli_get_server_info($this->db_connect);
        $pref = preg_replace("/(\d{1,2}\.\d{1,2})\.(\d{1,3})(.+)/", "\\2", $mysql_ver);
        $mysql_ver = preg_replace("/(\d{1,2}\.\d{1,2})(.+)/", "\\1", $mysql_ver);
        $mysql_ver = $mysql_ver * 1;
        if ($mysql_ver >= 4.1 && $pref > 0) {
            $sql = "SET character_set_client='$charset'";
            $this->query($sql, 1);
            if (!$this->db_storage) $this->getClose();
            $sql = "SET character_set_results='$charset'";
            $this->query($sql, 1);
            if (!$this->db_storage) $this->getClose();
            $sql = "SET collation_connection='".$charset."_general_ci'";
            $this->query($sql, 1);
            if (!$this->db_storage) $this->getClose();
        }
    }

    /**
     * Получение списка таблиц в БД
     * @return array
     */
    public function getTableList () {
        $sql = "SHOW TABLES FROM ".$this->db_name;
        $this->db_Tables = $this->getResults($sql, 4);
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
        if (!in_array($table, $this->db_Tables)) $this->getTableList();
        if (!in_array($table, $this->db_Tables)) {
            $this->DB_Error("Could not create List Fields: Table - $table not exists", $code);
            return false;
        }
        elseif (isset($this->error_code[$code]) && $this->error_code[$code]) unset($this->error_code[$code]);
        if (!isset($this->db_TableList[$table])) {
            $sql = "SHOW COLUMNS FROM $table";
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
        if ($this->log_all) $this->logs[] = "Create INSERT QUERY: ".$sql." - success";
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
        if ($this->log_all) $this->logs[] = "Create UPDATE QUERY: ".$sql." - success";
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
                if (in_array($key,$tab_fields)) {
                    if ($value == 'NULL') $ind = ($ind)?"$ind AND `$key` IS NULL":"`$key` IS NULL";
                    elseif ($value == 'NOT NULL') $ind = ($ind)?"$ind AND `$key` IS NOT NULL":"`$key` IS NOT NULL";
                    else $ind = ($ind)?"$ind AND `$key` = '$value'":"`$key` = '$value'";
                }
            }
        }
        if ($ind) $ind = "WHERE $ind";
        $sql = "DELETE FROM $table $ind";
        if ($this->log_all) $this->logs[] = "Create DELETE QUERY: ".$sql." - success";
        return $sql;
    }

    /**
     * Выполнение SQL запросов из файла
     *
     * @param string $SQLFile - путь и имя вызываемого файла
     * @return bool
     */
    public function getQueryFile ($SQLFile = "db.sql") {
        if (file_exists($SQLFile) ) {
            //set_magic_quotes_runtime(0);
            $fileSQL = file_get_contents($SQLFile);
            $fileSQL = preg_split("/\n/", $fileSQL);
            $i = 0;
            $SQL = array();
            foreach ($fileSQL as $n=>$line) {
                if (!preg_match("/^--/", trim($line)) && trim($line)) {
                    $SQL[$i] = $line;
                    $i++;
                }
            }
            $strSQL = join ("", $SQL);
            $arrSQL = preg_split(";", $strSQL);
            if ($this->getDB()) {
                for ($i=0; $i < count($arrSQL); $i++) {
                    if (strlen(trim($arrSQL[$i])) > 1) {
                        if (!$this->query($arrSQL[$i], 1)) return false;
                    }
                }
            }
            else {
                return false;
            }
        }
        else {
            $this->DB_Error("Could not create query. File not found: $SQLFile.");
            return false;
        }
        if (!$this->db_storage) $this->getClose();
        return true;
    }

    /**
     * Возвращает ID последней добавленной записи в таблицу
     * @param string $table - имя таблицы, если не указано, то возвращает последний ID во всей БД
     * @return mixed
     */
    public function lastID ($table = '') {
        if (!$table) return$this->getResults("SELECT LAST_INSERT_ID()", 1);
        else $res = $this->getResults("SELECT LAST_INSERT_ID() FROM $table", 1);
        return $res;
    }

    /**
     * Обработка ошибок.
     * Вывод на экран, сохранение в переменную error.
     * @param bool $message - сообщение об ошибке
     * @param string $code - код ошибки
     * @return bool
     */
    private function DB_Error ($message=false, $code = '') {

        if ($code && isset($this->error_code[$code])) return false;
        elseif ($code) $this->error_code[$code] = true;

        if (is_object($this->db_connect)) $message_bd = htmlentities(@mysqli_error($this->db_connect));
        else $message_bd = '';
        list($mess) = preg_split("/:/", $message);
        $query = htmlentities(trim(strtr($message, array($mess.":"=>''))));
        $message = $mess;
        if ($query && strlen(trim($query))) $message .= ". QUERY: ".$query;
        $message .= " ERROR: ".$message_bd;

        parent::Error($message, 'MySQL');

        if ($this->error_exit) {
            if (!$this->db_storage) $this->getClose();
            exit;
        }
        return false;
    }
}