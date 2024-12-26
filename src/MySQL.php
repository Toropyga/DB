<?php

/**
 * Class for working with MySQL database
 * @author FYN
 * Date: 15/04/2005
 * @version 5.1.1
 * @copyright 2005-2024
 */

namespace FYN\DB;

use mysqli_result;

class MySQL extends AbstractDB {

    /**
     * Host name or address
     * @var string
     */
    private $db_host; //Host name
    /**
     * Port
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
     * Log all actions (true) or only errors (false)
     * @var bool
     */
    private $log_all = true;
    /**
     * DB connect
     * @var object
     */
    private $db_connect = [];
    /**
     * Connect status
     * @var bool
     */
    public $status = false;
    /**
     * Maintain connection for entire session or connect on every SQL query
     * @var bool
     */
    private $db_storage = true;
    /**
     * List of filds in tables
     * @var array
     */
    private $db_TableList = array();
    /**
     * List of tables in DB
     * @var array
     */
    private $db_Tables = array();
    /**
     * Use transaction
     * @var bool
     */
    private $use_transaction = true;

    /**
     * DBMySQL constructor.
     * Class for working with MySQL database
     * @param mixed $HOST - host
     * @param mixed $PORT - port
     * @param mixed $NAME - DB namee
     * @param mixed $USER - user name
     * @param mixed $PASS - user password
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
     * Class destructor.
     */
    public function __destruct() {
        $this->getClose();
        $this->status = false;
    }

    /**
     * Basic function for select queries
     * Set SQL query to DataBase and return query Result
     *
     * @param string $sql - SQL query to DataBase
     * @param int|string $one - return result parameter
     * Can take values:
     *  Numeric:
     *      0 or '' - (selection: any number of rows and columns) expect an array of associative arrays ([] => array(field_name => value));
     *      1 - (selection: one row / one column) expect a row, if the selection yielded more than one column - returns an associative array (field_name => value), if more than one row - returns an array of values ​] => value), if more than one row and more than one column - an array of associative arrays ([] => array(field_name => value));
     *      2 - (selection: one row / many columns) expect an associative array (field_name => value), if more than one row and one column - returns an array of values ​] => value), if more than one row and more thgan one column - an array of associative arrays ([] => array(field_name => value));
     *      3 - (selection: multiple rows / one column) expect an associative array of arrays (field_name => array([] => value), if more than one row and more than one column - an array of associative arrays ([] => array(field_name => value));
     *      4 - (selection: multiple rows / one column) expect an array of values ​[] => value), if more than one row and more than one column - an array of associative arrays ([] => array(field_name => value)).
     *      5 - (selection: multiple rows / 2 columns) expect an array of values ​value of field 1] => value of field 2)
     *      6 - (selection: multiple rows / 2 columns) expect an array of values ​value of field 1] => value of field 2), if [value of field 1] is repeated, the array becomes [value of field 1] => array([0] => value of field 2, [1] => field value 2...)
     *      7 - return data on query execution EXPLAIN
     *  String (analogous to numeric):
     *      'all' or '' - (selection: any number of rows and columns) expect an array of associative arrays ([] => array(field_name => value));
     *      'one' - (selection: one row / one column) expect a row, if the selection yielded more than one column - returns an associative array (field_name => value), if more than one row - returns an array of values ​] => value), if more than one row and more than one column - an array of associative arrays ([] => array(field_name => value));
     *      'row' - (selection: one row / many columns) expect an associative array (field_name => value), if more than one row and one column - returns an array of values ​] => value), if more than one row and more thgan one column - an array of associative arrays ([] => array(field_name => value));
     *      'column' - (selection: multiple rows / one column) expect an associative array of arrays (field_name => array([] => value), if more than one row and more than one column - an array of associative arrays ([] => array(field_name => value));
     *      'col' - (selection: multiple rows / one column) expect an array of values ​[] => value), if more than one row and more than one column - an array of associative arrays ([] => array(field_name => value)).
     *      'dub' - (selection: multiple rows / 2 columns) expect an array of values ​value of field 1] => value of field 2)
     *      'dub_all' - (selection: multiple rows / 2 columns) expect an array of values ​value of field 1] => value of field 2), if [value of field 1] is repeated, the array becomes [value of field 1] => array([0] => value of field 2, [1] => field value 2...)
     *      'explain' - return data on query execution EXPLAIN
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
     * DB MySQL connect
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
     * Close connect
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
     * Run SQL query
     * @param string $sql - SQL query
     * @param integer $other_function - execute a request from another function (not a direct request, close the connection)
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
     * Processing the result and forming an array of received data
     * @param $res - data object
     * @param int $one - processing parameter (see getResults)
     * @return array
     */
    private function res2array ($res, $one = 0) { // Get query results to array
        $result = array();
        if (is_array($res)) return $res;
        if (is_resource($res) || is_object($res) || $this->use_transaction) {
            if ($this->use_transaction && !mysqli_num_rows ($res)) return $result;
            elseif (!$this->use_transaction && !mysqli_num_rows ($res)) return $result;
            while ($row = mysqli_fetch_assoc ($res)) {
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
     * Database select
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
     * Set charset
     * @param string $charset - charset
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
     * Getting a list of tables
     * @return array
     */
    public function getTableList () {
        $sql = "SHOW TABLES FROM ".$this->db_name;
        $this->db_Tables = $this->getResults($sql, 4);
        return $this->db_Tables;
    }

    /**
     * Getting a list of fields in table
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
        $sql = "INSERT INTO $table ($fields) VALUES ($val)";
        if ($this->log_all) $this->logs[] = "Create INSERT QUERY: ".$sql." - success";
        return $sql;
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
        $sql = "UPDATE $table SET $fields $ind";
        if ($this->log_all) $this->logs[] = "Create UPDATE QUERY: ".$sql." - success";
        return $sql;
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
        $sql = "DELETE FROM $table $ind";
        if ($this->log_all) $this->logs[] = "Create DELETE QUERY: ".$sql." - success";
        return $sql;
    }

    /**
     * Executing SQL queries from a file
     *
     * @param string $SQLFile - path and name of file
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
     * Returns the ID of the last record added to the table.
     * @param string $table - table name, if not specified, returns the last ID in the entire database
     * @param array $index - array of WHERE condition data in the format array(['field_name'] => 'value');
     * @return mixed
     */
    public function lastID ($table = '', $index = array()) {
        $code = 'lastID';
        if (!$table) {
            if (!$res = mysqli_insert_id($this->db_connect)) $res = $this->getResults("SELECT LAST_INSERT_ID() LIMIT 0,1", 1);
        }
        else {
            $ind = '';
            if (sizeof($index)) {
                if (!$tab_fields = $this->getListFields($table)) return FALSE;
                if (!is_array($index)) {
                    $this->DB_Error("Could not create SELECT LAST ID query: Error keys - $index (not array)", $code);
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
                if ($ind) $ind = "WHERE $ind";
                $ind_full = $this->getResults("SHOW KEYS FROM $table");
                $in = array();
                foreach ($ind_full as $row) {
                    $in[] = $row['Column_name'];
                }
                if (sizeof($in) > 1) {
                    $str = implode(',', $in);
                    $res_str = $this->getResults("SELECT MAX(concat_ws(',', $str)) FROM $table $ind LIMIT 0,1", 1);
                    $in_spl = explode(',', $res_str);
                    $res = array();
                    foreach ($in as $k=>$v) $res[$v] = $in_spl[$k];
                }
                else $res = $this->getResults("SELECT $in[0] FROM $table $ind LIMIT 0,1", 1);
            }
            else $res = $this->getResults("SELECT LAST_INSERT_ID() FROM $table LIMIT 0,1", 1);
        }
        return $res;
    }

    /**
     * Error handling.
     * Output to screen, save to error variable.
     * @param string $message - error message
     * @param string $code - error code
     * @return bool
     */
    private function DB_Error ($message='', $code = '') {

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