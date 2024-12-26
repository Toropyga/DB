<?php
/**
 * A generic class that uses the PDO library.
 * !!! In development !!!
 * @author FYN
 * Date: 16/09/2019
 * @version 0.1.5
 * @copyright 2019-2024
 */

namespace FYN\DB;

use PDO, PDOException;

class PDO_LIB extends AbstractDB {
    /**
     * The type of database we are connecting to
     * @var string
     */
    private $db_type = 'mysql';
    /**
     * Array of supported database types
     * @var array
     */
    private $db_types = ['mysql', 'pgsql', 'oci', 'odbc']; // todo sqlite,
    /**
     * Host name or address
     * @var string
     */
    private $db_host; //Host name
    /**
     * Server port
     * @var integer
     */
    private $db_port; //Port number
    /**
     * DB name
     * @var string
     */
    private $db_name; //Database name
    /**
     * User name
     * @var string
     */
    private $db_user; //User name
    /**
     * User password
     * @var string
     */
    private $db_pass; //User password
    /**
     * Database encoding
     * @var string
     */
    private $db_charset = 'utf8';
    /**
     * The type of record used to connect to Oracle:
     *      0 - only the DB name is used
     *      1 - host and DB name is used
     *      2 - full entry is used for connection
     * @var int
     */
    private $oracle_connect_type = 0;
    /**
     * DB connection
     * @var object
     */
    private $db_connect;
    /**
     * List of fields in tables
     * @var array
     */
    private $db_TableList = array();
    /**
     * List of tables in DB
     * @var array
     */
    private $db_Tables = array();
    /**
     * DB connection status
     * @var bool
     */
    public $status = false;
    /**
     * Service variable for interaction with PDO
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
     * Write to log.
     * Class destructor.
     */
    public function __destruct() {
        $this->status = false;
    }

    /**
     * Basic function for select queries
     * Set SQL query to DataBase and return query Result
     *
     * @param string $sql - SQL query to DataBase
     * @param int|string $one - return result parameter
     * *  Numeric:
     *      0 or '' - (selection: any number of rows and columns) expect an array of associative arrays ([] => array(field_name => value));
     *      1 - (selection: one row / one column) expect a row, if the selection yielded more than one column - returns an associative array (field_name => value), if more than one row - returns an array of values ​] => value), if more than one row and more than one column - an array of associative arrays ([] => array(field_name => value));
     *      2 - (selection: one row / many columns) expect an associative array (field_name => value), if more than one row and one column - returns an array of values ​] => value), if more than one row and more thgan one column - an array of associative arrays ([] => array(field_name => value));
     *      3 - (selection: multiple rows / one column) expect an associative array of arrays (field_name => array([] => value), if more than one row and more than one column - an array of associative arrays ([] => array(field_name => value));
     *      4 - (selection: multiple rows / one column) expect an array of values ​[] => value), if more than one row and more than one column - an array of associative arrays ([] => array(field_name => value)).
     *      5 - (selection: multiple rows / 2 columns) expect an array of values ​value of field 1] => value of field 2)
     *      6 - (selection: multiple rows / 2 columns) expect an array of values ​value of field 1] => value of field 2), if [value of field 1] is repeated, the array becomes [value of field 1] => array([0] => value of field 2, [1] => field value 2...)
     *  String (analogous to numeric):
     *      'all' or '' - (selection: any number of rows and columns) expect an array of associative arrays ([] => array(field_name => value));
     *      'one' - (selection: one row / one column) expect a row, if the selection yielded more than one column - returns an associative array (field_name => value), if more than one row - returns an array of values ​] => value), if more than one row and more than one column - an array of associative arrays ([] => array(field_name => value));
     *      'row' - (selection: one row / many columns) expect an associative array (field_name => value), if more than one row and one column - returns an array of values ​] => value), if more than one row and more thgan one column - an array of associative arrays ([] => array(field_name => value));
     *      'column' - (selection: multiple rows / one column) expect an associative array of arrays (field_name => array([] => value), if more than one row and more than one column - an array of associative arrays ([] => array(field_name => value));
     *      'col' - (selection: multiple rows / one column) expect an array of values ​[] => value), if more than one row and more than one column - an array of associative arrays ([] => array(field_name => value)).
     *      'dub' - (selection: multiple rows / 2 columns) expect an array of values ​value of field 1] => value of field 2)
     *      'dub_all' - (selection: multiple rows / 2 columns) expect an array of values ​value of field 1] => value of field 2), if [value of field 1] is repeated, the array becomes [value of field 1] => array([0] => value of field 2, [1] => field value 2...)
     *
     * @return array|bool|string SQL query result
     */
    public function getResults ($sql, $one=0) { // Get query results
        $this->query($sql);
        $one = parent::checkReturnType($one);
        if ($one === false) {
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
     * Processing the result and forming an array of received data
     * @param int $one - processing parameter (see getResults)
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
                    if (!$index) $index = 'no_value_' . $idx;
                    $result[$index] = $value;
                } else $result[] = $row;
            }
        }
        elseif ($col_count == 2 && $one == 6) {
            //alternative data processing option (selection: many rows / 2 columns) taking into account duplicate keys and values
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
     * Setting the type of record used to connect to Oracle:
     *      0 - only the DB name is used
     *      1 - host and DB name is used
     *      2 - full entry is used for connection
     * @param int $type
     */
    private function setOracleConnectType ($type = 0) {
        if ($type != 1 || $type != 2) $this->oracle_connect_type = 0;
        else $this->oracle_connect_type = $type;
    }

    /**
     * Initiating a connection to a database
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
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_WARNING,
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
     * Preparing a database query using the PDO module rules
     * @param $sql - query
     * @param array $values - parameters to send
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
     * Executing a previously prepared query
     * @param $values - values ​​substituted into the prepared query
     * @param mixed $pdo - PDO module object from prepare function
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
     * Row-by-row data extraction
     * @param string $mode - sampling parameters
     * @param mixed $pdo - PDO module object from prepare or query function
     * @return bool
     */
    public function fetch ($mode = '', $pdo = '') {
        if (is_object($pdo) && method_exists($pdo, 'fetch')) return $pdo->fetch($mode);
        elseif (is_object($this->pdo) && method_exists($this->pdo, 'fetch')) return $this->pdo->fetch($mode);
        return false;
    }

    /**
     * Fetching an array of all data
     * @param string $mode - sampling parameters
     * @param mixed $pdo - PDO module object from prepare or query function
     * @return bool
     */
    public function fetchAll ($mode = '', $pdo = '') {
        if (is_object($pdo) && method_exists($pdo, 'fetchAll')) return $pdo->fetchAll($mode);
        elseif (is_object($this->pdo) && method_exists($this->pdo, 'fetchAll')) return $this->pdo->fetchAll($mode);
        return false;
    }

    /**
     * Executing SQL query to the Database
     * @param $sql - query
     * @return string
     */
    public function query ($sql) {
        $code = 'query';
        try {
            $run_time = time();
            $this->pdo = $this->db_connect->query($sql);
            $this->run_time = time()-$run_time;
            if (isset($this->error_code[$code]) && $this->error_code[$code]) unset($this->error_code[$code]);
        }
        catch (PDOException $e) {
            $message = "Could not query: $sql\nError: ".$e->getMessage();;
            return $this->DB_Error($message, $code);
        }
        return $this->pdo;
    }

    /**
     * Setting up sampling parameters
     * @param int $mode - sampling parameters
     * @param mixed $param_1 - first group of additional parameters
     * @param mixed $param_2 - second group of additional parameters
     * @param mixed $pdo - PDO module object from prepare or query function
     * @return bool
     */
    public function setFetchMode ($mode = PDO::FETCH_ASSOC, $param_1 = '', $param_2 = '', $pdo = '') {
        if (is_object($pdo) && method_exists($pdo, 'setFetchMode')) return $pdo->setFetchMode($mode, $param_1, $param_2);
        if (is_object($this->pdo) && method_exists($this->pdo, 'setFetchMode')) return $this->pdo->setFetchMode($mode, $param_1, $param_2);
        return false;
    }

    /**
     * Getting a list of tables in a database
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
     * Getting a list of existing fields in a table
     * @param $table - table name
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
     * Creating a Single Insert Query
     * @param string $table - table name
     * @param array $values - array of data to add in the format array(['field_name'] => 'value');
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
        return "INSERT INTO $table ($fields) VALUES ($val)";
    }

    /**
     * Creating an Update query
     * @param string $table - table name
     * @param array $values - array of data for update in the format array(['field_name'] => 'value');
     * @param mixed $index - array of WHERE condition data in the format array(['field_name'] => 'value');
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
        return "UPDATE $table SET $fields $ind";
    }

    /**
     * Creating a Delete query
     * @param string $table - table name
     * @param mixed $index - array of WHERE condition data in the format array(['field_name'] => 'value');
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
        return "DELETE FROM $table $ind";
    }

    /**
     * Returns the ID of the last record added to the table.
     * @param string $name - table name or sequence object name that should return the ID (pgsql), if not specified, returns the last ID in the entire DB
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
     * Error handling.
     * Output to screen, send to administrator by email, save to error variable.
     * @param bool $message - error message
     * @param string $code - error code
     * @param mixed $pdo - connection object
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