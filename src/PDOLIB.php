<?php

declare(strict_types=1);

/**
 * A generic class that uses the PDO library.
 * @author Yuri Frantsevich
 * @version 3.0.2
 * @copyright 2019-2026
 */

namespace Toropyga\DB;

use PDO, PDOException;

class PDOLIB extends AbstractDB {
    /**
     * The type of database we are connecting to
     * @var string
     */
    private $db_type = 'mysql';
    /**
     * Array of supported database types
     * @var array
     */
    private $db_types = ['mysql', 'pgsql', 'oci', 'odbc'];
    /**
     * Host name or address
     * @var string
     */
    private $db_host;
    /**
     * Server port
     * @var integer
     */
    private $db_port;
    /**
     * DB name
     * @var string
     */
    private $db_name;
    /**
     * User name
     * @var string
     */
    private $db_user;
    /**
     * User password
     * @var string
     */
    private $db_pass;
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
    private $db_connect = null;
    /**
     * List of fields in tables
     * @var array
     */
    private $db_TableList = [];
    /**
     * List of tables in DB
     * @var array
     */
    private $db_Tables = [];
    /**
     * DB connection status
     * @var bool
     */
    public $status = false;
    /**
     * Service variable for interaction with PDO
     * @var string
     */
    private $pdo = null;

    public function __construct($db_type = false, $NAME = false, $USER = false, $PASS = false, $HOST = false, $PORT = false, $oracle_connect_type = false) {
        if (defined('\DB_PDO_TYPE') && !$db_type && in_array(\DB_PDO_TYPE, $this->db_types)) $this->db_type = \DB_PDO_TYPE;
        elseif ($db_type && in_array($db_type, $this->db_types)) $this->db_type = $db_type;
        if (defined('\DB_PDO_HOST') && !$HOST) $this->db_host = \DB_PDO_HOST; elseif ($HOST) $this->db_host = $HOST;
        if (defined('\DB_PDO_PORT') && !$PORT) $this->db_port = \DB_PDO_PORT; elseif ($PORT) $this->db_port = $PORT;
        if (defined('\DB_PDO_NAME') && !$NAME) $this->db_name = \DB_PDO_NAME; elseif ($NAME) $this->db_name = $NAME;
        if (defined('\DB_PDO_USER') && !$USER) $this->db_user = \DB_PDO_USER; elseif ($USER) $this->db_user = $USER;
        if (defined('\DB_PDO_PASS') && !$PASS) $this->db_pass = \DB_PDO_PASS; elseif ($PASS) $this->db_pass = $PASS;
        if (defined('\DB_PDO_DEBUG')) $this->debug = \DB_PDO_DEBUG;
        if (defined('\DB_PDO_ERROR_EXIT')) $this->error_exit = \DB_PDO_ERROR_EXIT;
        if (defined("\DB_PDO_ORACLE_CONNECT_TYPE")) $oracle_connect_type = \DB_PDO_ORACLE_CONNECT_TYPE;
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
     *      'explain' - return data on query execution plan (not supported for the 'odbc' driver)
     *
     * @return mixed SQL query result
     */
    public function getResults (string $sql, int|string $one = 0): mixed {
        $one = parent::checkReturnType($one);
        if ($one === false) {
            $this->logs[] = "Wrong parameter ONE: ".$one;
            $one = 0;
        }
        if ($one == 7) return $this->getExplainPlan($sql);
        if (!$this->query($sql)) return ($one === 1) ? '' : [];
        $result = [];
        if (is_object($this->pdo) && method_exists($this->pdo, 'columnCount')) {
            $col_count = $this->pdo->columnCount();
            if (!$col_count && $one != 1) return [];
            elseif (!$col_count && $one == 1) $result = '';
            else $result = $this->res2array($one);
        }
        return $result;
    }

    /**
     * Execute a SELECT query and return the results
     * @param string $sql - SQL query
     * @param array $values - Parameter values for the query
     * @param int $one - Return type
     * @return mixed SQL query result
     */
    public function getQuery (string $sql, array $values = [], int|string $one = 0) {
        $one = parent::checkReturnType($one);
        if ($one === false) {
            $this->logs[] = "Wrong parameter ONE: ".$one;
            $one = 0;
        }
        if ($one == 7) return $this->getExplainPlan($sql, $values);
        // Executed via real bound parameters (prepare()/execute()), the same
        // mechanism already used by setInsert()/setUpdate()/setDelete(), rather
        // than substituting escaped values into the SQL text by hand. This is
        // the actual injection defence — getQuerySQL()/escapeString() remain
        // available separately for callers who just want to see/log the
        // resulting SQL text, not to execute it.
        if (!$this->prepare($sql)) return ($one === 1) ? '' : [];
        if (!$this->execute($values)) return ($one === 1) ? '' : [];
        $col_count = (is_object($this->pdo) && method_exists($this->pdo, 'columnCount'))
            ? $this->pdo->columnCount() : 0;
        if (!$col_count) return ($one === 1) ? '' : [];
        return $this->res2array($one);
    }

    /**
     * Return EXPLAIN/execution-plan output for $sql, as a flat array of plan
     * rows/lines - matching the shape MySQL.php's 'explain'/7 mode returns.
     *
     * - mysql/pgsql both understand a plain `EXPLAIN <sql>` prefix and return
     *   the plan as an ordinary result set, so it's just run like any SELECT.
     * - oci has no single-statement EXPLAIN: `EXPLAIN PLAN FOR <sql>` populates
     *   PLAN_TABLE for the session, and the formatted plan is then read back via
     *   `DBMS_XPLAN.DISPLAY()`. Unlike Oracle.php, PDOLIB keeps one PDO
     *   connection open for the object's whole lifetime rather than
     *   reconnecting per query, so both statements naturally run on the same
     *   session and there's no PLAN_TABLE session-affinity issue to work around.
     * - odbc has no EXPLAIN syntax that's portable across ODBC backends, so
     *   it's not supported here; an empty array is returned and logged.
     *
     * @param string $sql
     * @param array $values - bound parameter values, if $sql uses :name placeholders
     * @return array
     */
    private function getExplainPlan (string $sql, array $values = []): array {
        switch ($this->db_type) {
            case 'oci':
                $planSql = "EXPLAIN PLAN FOR ".$sql;
                $planned = $values ? ($this->prepare($planSql) && $this->execute($values)) : $this->query($planSql);
                if (!$planned) return [];
                if (!$this->query("SELECT PLAN_TABLE_OUTPUT FROM TABLE(DBMS_XPLAN.DISPLAY())")) return [];
                return array_values($this->res2array(4));
            case 'odbc':
                $this->logs[] = "'explain' (7) is not supported for the odbc driver - no portable EXPLAIN syntax across ODBC backends";
                return [];
            case 'pgsql':
            case 'mysql':
            default:
                $explainSql = "EXPLAIN ".$sql;
                $executed = $values ? ($this->prepare($explainSql) && $this->execute($values)) : $this->query($explainSql);
                if (!$executed) return [];
                return $this->res2array(0);
        }
    }

    /**
     * Get the SQL query with parameters substituted
     * @param string $sql - SQL query
     * @param array $values - Parameter values for the query
     * @return string
     */
    public function getQuerySQL (string $sql, array $values = []) {
        return preg_replace_callback('/:([A-Za-z_][A-Za-z0-9_]*)/', function ($match) use ($values) {
            $key = $match[1];
            if (!array_key_exists($key, $values)) return $match[0];
            $value = $values[$key];
            if ($value === null || $value === 'NULL') return 'NULL';
            if (is_array($value)) $value = json_encode($value);
            return $this->escapeString((string) $value);
        }, $sql);
    }

    /**
     * Processing the result and forming an array of received data
     * @param int $one - processing parameter (see getResults)
     * @return array
     */
    private function res2array ($one = 0) {
        $result = [];
        // Fetch all rows because rowCount() is unreliable for SELECT statements
        // on several PDO drivers.
        if (is_object($this->pdo) && method_exists($this->pdo, 'columnCount') && method_exists($this->pdo, 'fetchAll')) {
            $col_count = $this->pdo->columnCount();
            $rows = $this->pdo->fetchAll(PDO::FETCH_ASSOC);
            $row_count = count($rows);
        }
        else {
            $col_count = $row_count = 0;
            $rows = [];
        }
        if ($col_count == 1 && $row_count == 1 && $one == 1) {
            $result = array_values($rows[0])[0];
        }
        elseif ($row_count == 1 && $one == 2) $result = $rows[0];
        elseif ($col_count && $one >= 3 && $one <= 5) {
            foreach ($rows as $row) {
                if ($one && (is_array($row) || is_object($row)) && count($row) === 1) {
                    foreach ($row as $key => $value) {
                        if ($one == 3) $result[$key][] = $value;
                        else $result[] = $value;
                    }
                }
                elseif ($one == 5 && count($row) === 2) {
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
            // Collect duplicate keys instead of overwriting earlier values.
            $keys = [];
            $idx = 0;
            foreach ($rows as $row) {
                $row = array_values($row);
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
                            $result[$key] = [];
                            $result[$key][] = $value;
                            $result[$key][] = $val;
                        }
                    }
                }
                $idx++;
            }
        }
        elseif (($one == 1 || $one == 2) && $col_count == 1) {
            foreach ($rows as $row) $result[] = array_values($row)[0];
        }
        elseif ($one == 1 && $row_count == 1) $result = $rows[0];
        else $result = $rows;
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
        if ($type != 1 && $type != 2) $this->oracle_connect_type = 0;
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
            // Exceptions allow the adapter to centralize PDO error handling.
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
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
     * @param string $sql - query
     * @param array $values - parameters to send
     * @return string
     */
    public function prepare (string $sql, array $values = array(PDO::ATTR_CURSOR => PDO::CURSOR_FWDONLY)) {
        $code = 'prepare';
        $this->pdo = null;
        if (!$this->db_connect instanceof PDO) {
            return $this->DB_Error('Database is not connected.', $code);
        }
        try {
            $this->pdo = $this->db_connect->prepare($sql, $values);
            if (isset($this->error_code[$code]) && $this->error_code[$code]) unset($this->error_code[$code]);
        }
        catch (PDOException $e) {
            $message = "Could not prepare: $sql\nError: ".$e->getMessage();
            return $this->DB_Error($message, $code);
        }
        return $this->pdo;
    }

    /**
     * Executing a previously prepared query
     * @param mixed $values - values ​​substituted into the prepared query
     * @param mixed $pdo - PDO module object from prepare function
     * @return bool
     */
    public function execute ($values, $pdo = '') {
        $code = 'execute';
        $res = false;
        if (is_object($pdo) && method_exists($pdo, 'execute')) {
            try {
                $res = $pdo->execute($values);
                if (isset($this->error_code[$code]) && $this->error_code[$code]) unset($this->error_code[$code]);
            }
            catch (PDOException $e) {
                $message = "Could not execute.\nError: ".$e->getMessage();
                return $this->DB_Error($message, $code);
            }
        }
        elseif (is_object($this->pdo) && method_exists($this->pdo, 'execute')) {
            try {
                $res = $this->pdo->execute($values);
                if (isset($this->error_code[$code]) && $this->error_code[$code]) unset($this->error_code[$code]);
            }
            catch (PDOException $e) {
                $message = "Could not execute.\nError: ".$e->getMessage();
                return $this->DB_Error($message, $code);
            }
        }
        return $res;
    }

    /**
     * Row-by-row data extraction
     * @param string $mode - sampling parameters
     * @param mixed $pdo - PDO module object from prepare or query function
     * @return mixed
     */
    public function fetch ($mode = null, $pdo = '') {
        $target = (is_object($pdo) && method_exists($pdo, 'fetch')) ? $pdo
            : ((is_object($this->pdo) && method_exists($this->pdo, 'fetch')) ? $this->pdo : null);
        if (!$target) return false;
        return $mode === null ? $target->fetch() : $target->fetch($mode);
    }

    /**
     * Fetching an array of all data
     * @param string $mode - sampling parameters
     * @param mixed $pdo - PDO module object from prepare or query function
     * @return bool
     */
    public function fetchAll ($mode = null, $pdo = '') {
        $target = (is_object($pdo) && method_exists($pdo, 'fetchAll')) ? $pdo
            : ((is_object($this->pdo) && method_exists($this->pdo, 'fetchAll')) ? $this->pdo : null);
        if (!$target) return false;
        return $mode === null ? $target->fetchAll() : $target->fetchAll($mode);
    }

    /**
     * Executing SQL query to the Database
     * @param $sql - query
     * @return string
     */
    public function query (string $sql): mixed {
        $code = 'query';
        if (!$this->db_connect instanceof PDO) {
            return $this->DB_Error('Database is not connected.', $code);
        }
        try {
            $run_time = microtime(true);
            $this->pdo = $this->db_connect->query($sql);
            $this->run_time = microtime(true)-$run_time;
            if (isset($this->error_code[$code]) && $this->error_code[$code]) unset($this->error_code[$code]);
        }
        catch (PDOException $e) {
            $message = "Could not query: $sql\nError: ".$e->getMessage();
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
    public function getTableList (): array|false {
        switch ($this->db_type) {
            case 'oci':
                $sql = "SELECT table_name FROM user_tables";
                $this->db_Tables = $this->getResults($sql, 4);
                break;
            case 'pgsql':
                $sql = "SELECT table_name FROM information_schema.tables WHERE table_schema='public' AND table_type='BASE TABLE' AND table_catalog='".$this->db_name."'";
                $Tables = $this->getResults($sql, 4);
                if (!is_array($Tables)) return false;
                foreach ($Tables as $i=>$tableName) $this->db_Tables[$i] = $tableName;
                break;
            case 'odbc':
                $sql = "SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_TYPE = 'BASE TABLE' AND TABLE_CATALOG='".$this->db_name."'";
                $Tables = $this->getResults($sql, 4);
                if (!is_array($Tables)) return false;
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
     * @param string $table - table name
     * @return array|mixed
     */
    public function getListFields(string $table) {
        $table = $this->validateIdentifier((string) $table);
        $code = 'getListFields';
        $name_field = [];
        // FIX: Oracle stores unquoted identifiers upper-cased, so a lowercase
        // table name (the common PHP convention) never matched $this->db_Tables
        // (as returned by user_tables) under a case-sensitive comparison, even
        // though the table genuinely existed. Compare and cache case-
        // insensitively for 'oci', matching the same fix already applied in
        // Oracle.php. Other driver types keep the original exact-match
        // comparison.
        $isOci = $this->db_type === 'oci';
        $lookupTable = $isOci ? strtoupper($table) : $table;
        $tables = is_array($this->db_Tables) ? ($isOci ? array_map('strtoupper', $this->db_Tables) : $this->db_Tables) : null;
        if ($tables === null || !in_array($lookupTable, $tables, true)) {
            $this->db_Tables = $this->getTableList();
            if (!is_array($this->db_Tables)) return false;
            $tables = $isOci ? array_map('strtoupper', $this->db_Tables) : $this->db_Tables;
        }
        if (!in_array($lookupTable, $tables, true)) {
            $this->DB_Error("Could not create List Fields: Table - $table not exists", $code);
            return false;
        }
        if (!isset($this->db_TableList[$lookupTable])) {
            $field_key = 'Field';
            switch ($this->db_type) {
                case 'odbc':
                case 'pgsql':
                    $sql = "SELECT column_name FROM information_schema.columns WHERE table_name = ".$this->escapeString($table); // pgsql
                    $field_key = 'column_name';
                    break;
                case 'oci':
                    $sql = "SELECT column_name FROM user_tab_cols WHERE table_name = ".$this->escapeString($lookupTable); // oracle
                    $field_key = 'column_name';
                    break;
                case 'mysql':
                default:
                    $sql = "SHOW COLUMNS FROM $table"; // mysql
            }
            $fields = $this->getResults($sql, 4);
            if (!is_array($fields)) return false;
            foreach ($fields as $value) {
                $name_field[] = is_array($value) ? ($value[$field_key] ?? '') : $value;
            }
            $this->db_TableList[$lookupTable]=$name_field;
        }
        else {
            reset($this->db_TableList[$lookupTable]);
            $name_field = $this->db_TableList[$lookupTable];
        }
        return $name_field;
    }

    /**
     * Insert data to table
     * @param string $table - table name
     * @param array $values - array of data to add in the format array(['field_name'] => 'value');
     * @return string
     */
    public function setInsert ($table, $values) {
        $code = 'setInsert';
        if (!$tab_fields = $this->getListFields($table)) return FALSE;
        if (!is_array($values)) {
            $this->DB_Error("Could not create insert query: Error values - $values (not array)", $code);
            return false;
        }
        elseif (isset($this->error_code[$code]) && $this->error_code[$code]) unset($this->error_code[$code]);
        $fields = '';
        $val = '';
        $params = [];
        foreach ($values as $key => $value) {
            if (in_array($key,$tab_fields)) {
                $field = $this->quoteField($key);
                $fields = ($fields)?"$fields, $field":$field;
                if (is_array($value)) $value = json_encode($value);
                $val = ($val)?"$val, :$key":":$key";
                $params[$key] = $value;
            }
        }
        if (!$fields) return false;
        $sql = "INSERT INTO $table ($fields) VALUES ($val)";
        if (!$this->prepare($sql)) {
            return $this->DB_Error("Could not insert.", $code);
        }
        if (!$this->execute($params)) {
            $message = "Could not insert.";
            return $this->DB_Error($message, $code);
        }
        return true;
    }

    /**
     * Creating a Single Insert Query
     * @param string $table - table name
     * @param array $values - array of data to add in the format array(['field_name'] => 'value');
     * @return string
     */
    public function getInsertSQL (string $table, array $values): string|false {
        $table = $this->validateIdentifier($table);
        $code = 'getInsertSQL';
        if (!$tab_fields = $this->getListFields($table)) return FALSE;
        if (!is_array($values)) {
            $this->DB_Error("Could not create insert query: Error values - $values (not array)", $code);
            return false;
        }
        elseif (isset($this->error_code[$code]) && $this->error_code[$code]) unset($this->error_code[$code]);
        $fields = '';
        $val = '';
        foreach ($values as $key => $value) {
            if (in_array($key,$tab_fields)) {
                $field = $this->quoteField($key);
                $fields = ($fields)?"$fields, $field":$field;
                if ($value === null || $value === 'NULL') $value = 'NULL';
                else {
                    if (is_array($value)) $value = json_encode($value);
                    $value = $this->escapeString((string) $value); // already quoted
                }
                $val = ($val)?"$val, $value":"$value";
            }
        }
        return "INSERT INTO $table ($fields) VALUES ($val)";
    }

    /**
     * Update data into table
     * @param string $table - table name
     * @param array $values - array of data for update in the format array(['field_name'] => 'value');
     * @param mixed $index - array of WHERE condition data in the format array(['field_name'] => 'value');
     * @return string
     */
    public function setUpdate ($table, $values, $index = false) {
        $code = 'setUpdate';
        if (!$tab_fields = $this->getListFields($table)) return FALSE;
        if (!is_array($values)) {
            $this->DB_Error("Could not create update query: Error values - $values (not array)", $code);
            return false;
        }
        elseif (isset($this->error_code[$code]) && $this->error_code[$code]) unset($this->error_code[$code]);
        $fields = '';
        $val = [];
        foreach ($values as $key => $value) {
            if (in_array($key,$tab_fields)) {
                if (is_array($value)) $value = json_encode($value);
                $field = $this->quoteField($key);
                $fields = ($fields)?"$fields, $field = :$key":"$field = :$key";
                $val[$key] = $value;
            }
        }
        if (!$fields) return false;
        $ind = '';
        if ($index) {
            if (!is_array($index)) {
                $this->DB_Error("Could not create update query: Error keys - $index (not array)", $code);
                return false;
            }
            elseif (isset($this->error_code[$code]) && $this->error_code[$code]) unset($this->error_code[$code]);
            foreach ($index as $key => $value) {
                if (in_array($key,$tab_fields)) {
                    $field = $this->quoteField($key);
                    if ($value == 'NULL') $ind = ($ind)?"$ind AND $field IS NULL":"$field IS NULL";
                    elseif ($value == 'NOT NULL') $ind = ($ind)?"$ind AND $field IS NOT NULL":"$field IS NOT NULL";
                    else {
                        $n_key = 'index_'.$key;
                        $ind = ($ind)?"$ind AND $field = :$n_key":"$field = :$n_key";
                        $val[$n_key] = $value;
                    }
                }
            }
        }
        if ($ind) $ind = "WHERE $ind";
        $sql = "UPDATE $table SET $fields $ind";
        if (!$this->prepare($sql)) {
            return $this->DB_Error("Could not update.", $code);
        }
        if (!$this->execute($val)) {
            $message = "Could not update.";
            return $this->DB_Error($message, $code);
        }
        return true;
    }

    /**
     * Creating an Update query
     * @param string $table - table name
     * @param array $values - array of data for update in the format array(['field_name'] => 'value');
     * @param mixed $index - array of WHERE condition data in the format array(['field_name'] => 'value');
     * @return string
     */
    public function getUpdateSQL (string $table, array $values, array|false $index=false): string|false {
        $table = $this->validateIdentifier($table);
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
                if ($value === null || $value === 'NULL') $value = 'NULL';
                else {
                    if (is_array($value)) $value = json_encode($value);
                    $value = $this->escapeString((string) $value); // already quoted
                }
                $field = $this->quoteField($key);
                $fields = ($fields)?"$fields, $field = $value":"$field = $value";
            }
        }
        if (!$fields) return false;
        $ind = '';
        if ($index) {
            if (!is_array($index)) {
                $this->DB_Error("Could not create update query: Error keys - $index (not array)", $code);
                return false;
            }
            elseif (isset($this->error_code[$code]) && $this->error_code[$code]) unset($this->error_code[$code]);
            foreach ($index as $key => $value) {
                if (in_array($key,$tab_fields)) {
                    $field = $this->quoteField($key);
                    if ($value == 'NULL') $ind = ($ind)?"$ind AND $field IS NULL":"$field IS NULL";
                    elseif ($value == 'NOT NULL') $ind = ($ind)?"$ind AND $field IS NOT NULL":"$field IS NOT NULL";
                    else {
                        $value = is_array($value) ? json_encode($value) : $value;
                        $value = $this->escapeString((string) $value);
                        $ind = ($ind)?"$ind AND $field = $value":"$field = $value";
                    }
                }
            }
        }
        if ($ind) $ind = "WHERE $ind";
        return "UPDATE $table SET $fields $ind";
    }

    /**
     * Deleting from table
     * @param string $table - table name
     * @param mixed $index - array of WHERE condition data in the format array(['field_name'] => 'value');
     * @return string
     */
    public function setDelete ($table, $index=false) {
        $code = 'setDelete';
        if (!$tab_fields = $this->getListFields($table)) return false;
        $ind = '';
        $val = [];
        if ($index) {
            if (!is_array($index)) {
                $this->DB_Error("Could not create delete query: Error keys - $index (not array)");
                return FALSE;
            }
            elseif (isset($this->error_code[$code]) && $this->error_code[$code]) unset($this->error_code[$code]);
            foreach ($index as $key => $value) {
                if (in_array($key,$tab_fields)) {
                    $field = $this->quoteField($key);
                    if ($value == 'NULL') $ind = ($ind)?"$ind AND $field IS NULL":"$field IS NULL";
                    elseif ($value == 'NOT NULL') $ind = ($ind)?"$ind AND $field IS NOT NULL":"$field IS NOT NULL";
                    else {
                        $ind = ($ind)?"$ind AND $field = :$key":"$field = :$key";
                        $val[$key] = $value;
                    }
                }
            }
        }
        if ($ind) $ind = "WHERE $ind";
        $sql = "DELETE FROM $table $ind";
        if (!$this->prepare($sql)) {
            return $this->DB_Error("Could not delete.", $code);
        }
        if (!$this->execute($val)) {
            $message = "Could not delete.";
            return $this->DB_Error($message, $code);
        }
        return true;
    }

    /**
     * Creating a Delete query
     * @param string $table - table name
     * @param mixed $index - array of WHERE condition data in the format array(['field_name'] => 'value');
     * @return string
     */
    public function getDeleteSQL (string $table, array|false $index=false): string|false {
        $table = $this->validateIdentifier($table);
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
                    $field = $this->quoteField($key);
                    if ($value == 'NULL') $ind = ($ind)?"$ind AND $field IS NULL":"$field IS NULL";
                    elseif ($value == 'NOT NULL') $ind = ($ind)?"$ind AND $field IS NOT NULL":"$field IS NOT NULL";
                    else {
                        $value = is_array($value) ? json_encode($value) : $value;
                        $value = $this->escapeString((string) $value);
                        $ind = ($ind)?"$ind AND $field = $value":"$field = $value";
                    }
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
        if (!$this->db_connect instanceof PDO) return false;
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
        $errorInfo = null;
        if (is_object($this->db_connect) && method_exists($this->db_connect, 'errorInfo')) {
            $errorInfo = $this->db_connect->errorInfo();
        }
        if (!$errorInfo && is_object($pdo) && method_exists($pdo, 'errorInfo')) $errorInfo = $pdo->errorInfo();
        elseif (!$errorInfo && is_object($this->pdo) && method_exists($this->pdo, 'errorInfo')) $errorInfo = $this->pdo->errorInfo();
        $message_bd = is_array($errorInfo) ? ($errorInfo[2] ?? '') : (string) $errorInfo;
        $message_bd = htmlentities($message_bd ?: 'Unknown database error');
        $message = (string) $message;
        [$mess, $query] = array_pad(explode(':', $message, 2), 2, '');
        $query = htmlentities(trim($query));
        $message = $mess;
        if ($query && strlen(trim($query))) $message .= ". QUERY: ".$query;
        $message .= " ERROR: ".$message_bd;
        parent::Error($message, 'PDOLIB');
        return false;
    }
    
    /**
     * Escape string
     * @param string $string
     * @return string
     */
    /**
     * Escape a value for safe embedding in an SQL string and return it
     * already wrapped in quotes (a ready-to-use SQL literal), e.g. "'O''Brien'".
     *
     * NOTE: this exists only for the getInsertSQL()/getUpdateSQL()/getDeleteSQL()/
     * getQuerySQL() "build me a SQL string" helpers, which are meant for display,
     * logging, or handing to other tooling. Everything this class actually
     * *executes* (setInsert, setUpdate, setDelete, getQuery, prepare/execute)
     * uses real bound parameters via PDO and never touches this method — bound
     * parameters are the actual injection defence, this is a best-effort fallback
     * for callers who only want the literal SQL text.
     *
     * @param string $string
     * @return string Quoted SQL literal
     */
    private function escapeString ($string) {
        switch ($this->db_type) {
            case 'odbc':
            case 'oci':
            case 'mysql':
            default:
                // NB: $this->db_connect is a PDO instance here, not mysqli — it has
                // no real_escape_string(). PDO::quote() is the correct equivalent
                // and, unlike real_escape_string(), already returns the value
                // wrapped in quotes.
                return $this->db_connect->quote((string) $string);
            case 'pgsql':
                // pg_escape_string() does NOT add surrounding quotes, so add them
                // here to match the contract of this method (always return a
                // ready-to-embed literal).
                return "'" . pg_escape_string((string) $string) . "'";
        }
    }

    private function quoteField (string $field): string {
        return $this->quoteIdentifier($field, $this->db_type === 'mysql' ? '`' : '"');
    }
}