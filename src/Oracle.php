<?php

declare(strict_types=1);

/**
 * Class for working with Oracle database
 * @author Yuri Frantsevich
 * @version 3.0.0
 * @copyright 2019-2026
 */

namespace Toropyga\DB;

class Oracle extends AbstractDB {

    private $oracle;
    /**
     * List of existing tables in Oracle DB and their fields
     * @var array
     */
    private $db_TableListOracle = [];
    /**
     * DB Oracle connection configuration
     * @var array
     */
    private $oracle_config = array(
        'host'      => '',
        'user'      => '',
        'pass'      => '',
        'name'      => '',
        'port'      => NULL,
        'charset'   => 'AL32UTF8',
        'p_connect' => FALSE,
        'use_host'  => 2
    );
    /**
     * Log all actions (true) or only errors (false)
     * @var bool
     */
    private $log_all = true;
    /**
     * Error message
     * @var string
     */
    private $error_text = '';
    /**
     * Connection status
     * @var bool
     */
    public $status = false;
    /**
     * Requests are made to the package (true)
     * @var bool
     */
    private $package = true;
    /**
     * Execute the query and wait for the cursor to return (true)
     * @var bool
     */
    private $cursor = true;
    /**
     * array of query variables
     * @var array
     */
    private $sql_param = [];
    /**
     * Result
     * @var array
     */
    private $stat = [];

    /**
     * Client version
     * @var string
     */
    public $client_version = '';

    /**
     * Server version
     * @var string
     */
    public $server_version = '';

    /** Oracle bind types, initialized only when OCI8 is available. @var array */
    private $types = [];

    /**
     * DBOracle constructor.
     * @param string $HOST - host
     * @param string $NAME - DB name
     * @param string $USER - user name
     * @param string $PASS - user password
     * @param int $USE_HOST - the type of record used to connect to Oracle (takes a value of 0, 1 or 2), optimally 2
     * @param string $PORT - port
     * @param bool $P_CONNECT - maintain connection for entire session or connect on every SQL query
     * @param string $CHARSET - charset
     * @param bool $no_connect - don't connect to DB when class is initiated
     */
    public function __construct ($HOST=NULL, $NAME=NULL, $USER=NULL, $PASS=NULL, $USE_HOST=NULL, $PORT=NULL, $P_CONNECT=NULL, $CHARSET = '', $no_connect = false) {
        if (!extension_loaded('oci8')) {
            if ($this->log_all) $this->logs[] = "PHP OCI8 not installed!";
            $this->DBError("PHP OCI8 not installed!", '__construct');
            return;
        }

        if (defined('\DB_ORACLE_HOST') && !$HOST) $this->oracle_config['host'] = \DB_ORACLE_HOST; elseif ($HOST) $this->oracle_config['host'] = $HOST;
        if (defined('\DB_ORACLE_PORT') && !$PORT) $this->oracle_config['port'] = \DB_ORACLE_PORT; elseif ($PORT) $this->oracle_config['port'] = $PORT;
        if (defined('\DB_ORACLE_NAME') && !$NAME) $this->oracle_config['name'] = \DB_ORACLE_NAME; elseif ($NAME) $this->oracle_config['name'] = $NAME;
        if (defined('\DB_ORACLE_USER') && !$USER) $this->oracle_config['user'] = \DB_ORACLE_USER; elseif ($USER) $this->oracle_config['user'] = $USER;
        if (defined('\DB_ORACLE_PASS') && !$PASS) $this->oracle_config['pass'] = \DB_ORACLE_PASS; elseif ($PASS) $this->oracle_config['pass'] = $PASS;

        // An explicit connection mode takes precedence over the global default.
        if ($P_CONNECT === true || $P_CONNECT === false) $this->oracle_config['p_connect'] = $P_CONNECT;
        elseif (defined('\DB_ORACLE_STORAGE')) $this->oracle_config['p_connect'] = \DB_ORACLE_STORAGE;

        if (defined('\DB_ORACLE_CHARSET') && !$CHARSET) $this->oracle_config['charset'] = \DB_ORACLE_CHARSET; elseif ($CHARSET) $this->oracle_config['charset'] = $CHARSET;

        if (defined('\DB_ORACLE_DEBUG')) $this->debug = \DB_ORACLE_DEBUG;
        if (defined('\DB_ORACLE_ERROR_EXIT')) $this->error_exit = \DB_ORACLE_ERROR_EXIT;
        if (defined('\DB_ORACLE_LOG_NAME')) $this->log_file = \DB_ORACLE_LOG_NAME;
        if (defined('\DB_ORACLE_LOG_ALL')) $this->log_all = \DB_ORACLE_LOG_ALL;

        if ($USE_HOST === null && defined('\DB_ORACLE_USE_HOST') && in_array(\DB_ORACLE_USE_HOST, [0, 1, 2], true)) {
            $USE_HOST = \DB_ORACLE_USE_HOST;
        }
        if (in_array($USE_HOST, [0, 1, 2], true)) $this->oracle_config['use_host'] = $USE_HOST;

        $this->types = [SQLT_BFILEE, OCI_B_BFILE, SQLT_CFILEE, OCI_B_CFILEE, SQLT_CLOB, OCI_B_CLOB, SQLT_BLOB, OCI_B_BLOB, SQLT_RDD, OCI_B_ROWID, SQLT_NTY, OCI_B_NTY, SQLT_INT, OCI_B_INT, SQLT_CHR, SQLT_BIN, OCI_B_BIN, SQLT_LNG, SQLT_LBI, SQLT_RSET];
        if (defined("\SQLT_BOL")) {
            $this->types[] = SQLT_BOL;
            $this->types[] = OCI_B_BOL;
        }
        $this->client_version = oci_client_version();

        if (!$no_connect) $this->getOracle();
    }

    /**
     * Write to log
     * Class destructor.
     */
    public function __destruct() {
        $this->status = false;
    }

    /**
     * Sending a SQL query to an Oracle database and returning the query result
     *
     * @param string $sql - SQL query
     * @param int $one - return result parameter
     * Can take values:
     *  Numeric:
     *      0 or '' - (selection: any number of rows and columns) expect an array of associative arrays ([] => array(field_name => value));
     *      1 - (selection: one row / one column) expect a row, if the selection yielded more than one column - returns an associative array (field_name => value), if more than one row - returns an array of values ​] => value), if more than one row and more than one column - an array of associative arrays ([] => array(field_name => value));
     *      2 - (selection: one row / many columns) expect an associative array (field_name => value), if more than one row and one column - returns an array of values ​] => value), if more than one row and more thgan one column - an array of associative arrays ([] => array(field_name => value));
     *      3 - (selection: multiple rows / one column) expect an associative array of arrays (field_name => array([] => value), if more than one row and more than one column - an array of associative arrays ([] => array(field_name => value));
     *      4 - (selection: multiple rows / one column) expect an array of values ​[] => value), if more than one row and more than one column - an array of associative arrays ([] => array(field_name => value)).
     *      5 - (selection: multiple rows / 2 columns) expect an array of values ​value of field 1] => value of field 2)
     *      6 - (selection: multiple rows / 2 columns) expect an array of values ​value of field 1] => value of field 2), if [value of field 1] is repeated, the array becomes [value of field 1] => array([0] => value of field 2, [1] => field value 2...)
     *      7 - return data on query execution plan (EXPLAIN PLAN)
     *  String (analogous to numeric):
     *      'all' or '' - (selection: any number of rows and columns) expect an array of associative arrays ([] => array(field_name => value));
     *      'one' - (selection: one row / one column) expect a row, if the selection yielded more than one column - returns an associative array (field_name => value), if more than one row - returns an array of values ​] => value), if more than one row and more than one column - an array of associative arrays ([] => array(field_name => value));
     *      'row' - (selection: one row / many columns) expect an associative array (field_name => value), if more than one row and one column - returns an array of values ​] => value), if more than one row and more thgan one column - an array of associative arrays ([] => array(field_name => value));
     *      'column' - (selection: multiple rows / one column) expect an associative array of arrays (field_name => array([] => value), if more than one row and more than one column - an array of associative arrays ([] => array(field_name => value));
     *      'col' - (selection: multiple rows / one column) expect an array of values ​[] => value), if more than one row and more than one column - an array of associative arrays ([] => array(field_name => value)).
     *      'dub' - (selection: multiple rows / 2 columns) expect an array of values ​value of field 1] => value of field 2)
     *      'dub_all' - (selection: multiple rows / 2 columns) expect an array of values ​value of field 1] => value of field 2), if [value of field 1] is repeated, the array becomes [value of field 1] => array([0] => value of field 2, [1] => field value 2...)
     *      'explain' - return data on query execution plan (EXPLAIN PLAN)
     * @return mixed SQL query result
     */
    public function getResults (string $sql, int|string $one = 0): mixed {
        if (!$this->ensureConnected()) return false;
        $one = parent::checkReturnType($one);
        if ($one === false) {
            $this->logs[] = "Wrong parameter ONE: ".$one;
            $one = 0;
        }
        // 'explain'/7 has its own two-statement execution path (see getExplainPlan()),
        // so it's handled before the normal single-statement $sql is run.
        if ($one == 7) return $this->getExplainPlan($sql);
        $res = $this->query($sql);
        if ($one == 1) $result = '';
        else $result = [];
        if ($res && $col_row = sizeof($res)) {
            if ($col_row == 1 && $one && $one < 3) {
                if ($one != 1) foreach ($res as $row) $result = $row;
                else $result = join('', array_values($res[0]));
            }
            elseif (!$one) $result = $res;
            else $result = $this->res2array($res, $one);
        }
        elseif (!$res) return $res;
        return $result;
    }

    /**
     * Oracle has no single-statement EXPLAIN. `EXPLAIN PLAN FOR <sql>` populates
     * PLAN_TABLE for the current session instead of returning rows directly; the
     * formatted plan is then read back via `DBMS_XPLAN.DISPLAY()`. Both statements
     * must run on the SAME Oracle session, because PLAN_TABLE is typically a
     * session-private global temporary table. query() closes the connection after
     * every call unless p_connect is enabled (see ensureConnected()), so p_connect
     * is temporarily forced on for the duration of these two statements and the
     * connection is closed manually afterwards if it wasn't persistent to begin with.
     * @param string $sql
     * @return array flat array of plan-output lines, matching the shape returned
     *               by MySQL.php's 'explain'/7 mode
     */
    private function getExplainPlan (string $sql): array {
        $wasPersistent = $this->oracle_config['p_connect'];
        $this->oracle_config['p_connect'] = true;
        $planned = $this->query("EXPLAIN PLAN FOR ".$sql);
        $plan = ($planned !== false) ? $this->query("SELECT PLAN_TABLE_OUTPUT FROM TABLE(DBMS_XPLAN.DISPLAY())") : false;
        $this->oracle_config['p_connect'] = $wasPersistent;
        if (!$wasPersistent) $this->closeOracle();
        $result = [];
        if (is_array($plan)) {
            foreach ($plan as $row) {
                foreach ($row as $line) $result[] = $line;
            }
        }
        return $result;
    }

    /** Ensure a non-persistent connection is reopened before a query. @return bool */
    private function ensureConnected (): bool {
        if ($this->status) return true;
        if ($this->oracle_config['p_connect']) return false; // a persistent handle should already be open
        return $this->getOracle();
    }

    /**
     * DB Oracle connection
     * @return bool
     */
    public function getOracle () {
        $this->error = false;
        if (!$this->oracle_config['port']) $this->oracle_config['port'] = '1521';
        if ($this->oracle_config['use_host'] == 1) $db = $this->oracle_config['host']."/".$this->oracle_config['name'];
        elseif ($this->oracle_config['use_host'] == 2) $db = "(DESCRIPTION=(ADDRESS_LIST = (ADDRESS = (PROTOCOL = TCP)(HOST = ".$this->oracle_config['host'].")(PORT = ".$this->oracle_config['port'].")))(CONNECT_DATA=(SERVER=DEDICATED)(SERVICE_NAME=".$this->oracle_config['name'].")))";
        else $db = $this->oracle_config['name'];
        if ($this->oracle_config['p_connect']) $this->oracle = @oci_pconnect($this->oracle_config['user'], $this->oracle_config['pass'], $db, $this->oracle_config['charset']);
        else $this->oracle = @oci_connect($this->oracle_config['user'], $this->oracle_config['pass'], $db, $this->oracle_config['charset']);
        if (!$this->oracle) {
            $e = oci_error();
            $this->error_text = "Connect Error: ".$e['message']." :: SERVER: ".$this->oracle_config['host'];
            $this->logs[] = $this->error_text;
            $this->DBError($this->error_text, 'getOracle');
            $this->status = false;
            return false;
        }
        elseif (isset($this->error_code['getOracle']) && $this->error_code['getOracle']) unset($this->error_code['getOracle']);
        $this->status = true;
        $this->server_version = oci_server_version($this->oracle);
        return true;
    }

    /**
     * Forming a query to an Oracle package with a return to a variable before the query
     * @param $package - package name
     * @param $procedure - procedure name
     * @param string $query_args - string of arguments, separated by commas
     * @return string
     */
    public function getPackageQuery($package, $procedure, $query_args = '') {
        if ($package) return " Begin :res := ".$package.".".$procedure."(".$query_args."); End;";
        else return " Begin :res := ".$procedure."(".$query_args."); End;";
    }

    /**
     * Forming a query to an Oracle package with a return to a variable in the query
     * @param $package - package name
     * @param $procedure - procedure name
     * @param string $query_args - string of arguments, separated by commas
     * @return string
     */
    public function getProcedureQuery($package, $procedure, $query_args = '') {
        if ($package) return " Begin ".$package.".".$procedure."(".$query_args."); End;";
        else return " Begin ".$procedure."(".$query_args."); End;";
    }

    /**
     * Setting variables.
     * The input accepts an array of two types:
     *      1. array(':key' => 'value'),
     *          where ':key' - variable name, 'value' - value
     *      2. array(':key' => array('length' => '12345', 'type' => OCI_B_INT, 'value' => '555555')),
     *          where ':key' - variable name,
     *              the internal array contains fields:
     *                  'length' - maximum data size (-1 - current data size),
     *                  'type' - the data type that Oracle will cast values ​​to (see below),
     *                  'value' - value
     * Valid values ​​of types (https://www.php.net/manual/ru/function.oci-bind-by-name.php):
     *    SQLT_BFILEE or OCI_B_BFILE - for BFILE-objects;
     *    SQLT_CFILEE or OCI_B_CFILEE - for CFILE-objects;
     *    SQLT_CLOB or OCI_B_CLOB - for CLOB-objects;
     *    SQLT_BLOB or OCI_B_BLOB - for BLOB-objects;
     *    SQLT_RDD or OCI_B_ROWID - for ROWID-objects;
     *    SQLT_NTY or OCI_B_NTY - for named date types;
     *    SQLT_INT or OCI_B_INT - for integers;
     *    SQLT_CHR - for VARCHAR symbols;
     *    SQLT_BIN or OCI_B_BIN - for RAW-fields;
     *    SQLT_LNG - for LONG-fields;
     *    SQLT_LBI - for LONG RAW fields;
     *    SQLT_RSET - for cursors created by the oci_new_cursor() function;
     *    // SQLT_BOL or OCI_B_BOL - for PL/SQL BOOLEAN
     * @param array $bind
     */
    public function setBind ($bind = []) {
        $this->sql_param = $bind;
    }

    /**
     * Setting the query parameter
     * @param bool $package - true - packet request, false - direct request
     */
    public function setPackage ($package = true) {
        if ($package) $this->package = true;
        else $this->package = false;
    }

    /**
     * Set the parameter to work with the request
     * @param bool $cursor - true - return cursor, false - without cursor
     */
    public function setCursor ($cursor = true) {
        $this->cursor = $cursor;
    }

    /**
     * Executing a query to Oracle DB
     * @param $sql - query
     * @return array|false
     */
    public function query (string $sql): mixed {
        if (!$this->ensureConnected()) return false;
        $this->error = false;
        $stat = oci_parse($this->oracle, $sql);
        foreach ($this->sql_param as $key => $val) {
            if (str_contains($sql, $key)) {
                $n = strtr($key, array(':'=>''));
                $max_length = 4096;
                $type =  SQLT_CHR;
                if (is_array($val)) {
                    if (isset($val['length'])) $max_length = $val['length']*1;
                    if (isset($val['type']) && in_array($val['type'], $this->types, true)) $type = $val['type'];
                    if (!isset($val['value'])) $val['value'] = '';
                    if (in_array($type, array(SQLT_BFILEE, OCI_B_BFILE, SQLT_CFILEE, OCI_B_CFILEE, SQLT_CLOB, OCI_B_CLOB, SQLT_BLOB, OCI_B_BLOB, SQLT_RDD, OCI_B_ROWID))) {
                        if (in_array($type, array(SQLT_BFILEE, OCI_B_BFILE, SQLT_CFILEE, OCI_B_CFILEE))) $$n = oci_new_descriptor($this->oracle, OCI_D_FILE);
                        elseif (in_array($type, array(SQLT_CLOB, OCI_B_CLOB, SQLT_BLOB, OCI_B_BLOB))) $$n = oci_new_descriptor($this->oracle, OCI_D_LOB);
                        else $$n = oci_new_descriptor($this->oracle, OCI_D_ROWID);
                        $max_length = -1;
                    }
                    else $$n = $val['value'];
                }
                else $$n = $val;
                oci_bind_by_name($stat, $key, $$n, $max_length, $type);
            }
        }
        $hasCursor = $this->cursor && preg_match("/:res\s?(\W)/", $sql) === 1;
        $curs = $hasCursor ? oci_new_cursor($this->oracle) : false;
        if ($hasCursor && !isset($this->sql_param[':res'])) oci_bind_by_name($stat, ":res", $curs, -1, SQLT_RSET);
        if ($stat) {
            if ($hasCursor && $curs) {
                $run_time = microtime(true);
                if (@oci_execute($stat) && @oci_execute($curs)) {
                    $res = [];
                    while ($data = @oci_fetch_array($curs, OCI_ASSOC + OCI_RETURN_NULLS)) {
                        if (is_array($data)) {
                            foreach ($data as $key=>$row) {
                                if (is_object($row)) {
                                    $data_lob = $row->load();
                                    $row->free();
                                    if ($data_lob) {
                                        if (!isset($data['ORACLE_CLASS_READ_LOB'])) $data['ORACLE_CLASS_READ_LOB'] = $data_lob;
                                        else $data['ORACLE_CLASS_READ_LOB_FYN_DB'] = $data_lob;
                                    }
                                }
                            }
                            $res[] = $data;
                        }
                        elseif (is_object($data)) { // protect against a NULL LOB
                            $data_lob = $data->load();
                            $data->free();
                            if ($data_lob) $res[] = $data_lob;
                            else $res[] = $data;
                        }
                        else $res[] = $data;
                    }
                    @oci_free_statement($curs);
                    @oci_free_statement($stat);
                    if (isset($this->error_code['query']) && $this->error_code['query']) unset($this->error_code['query']);
                    $this->stat = [];
                    foreach ($this->sql_param as $key => $val) {
                        $n = strtr($key, array(':'=>''));
                        if (isset($$n))$this->stat[$n] = $$n;
                    }
                    if (count($this->stat)) $res = $this->stat;
                }
                else {
                    $this->error_text = $this->getOciErrorMessage($stat);
                    $this->logs[] = $this->error_text;
                    $this->DBError($this->error_text, 'query');
                    $res = false;
                }
                $this->run_time = microtime(true)-$run_time;
                if (isset($this->error_code['curs']) && $this->error_code['curs']) unset($this->error_code['curs']);
            }
            elseif (!$this->cursor) {
                $run_time = microtime(true);
                if (@oci_execute($stat)) {
                    $this->run_time = microtime(true)-$run_time;
                    $res = [];
                    while ($data = oci_fetch_array($stat, OCI_ASSOC + OCI_RETURN_NULLS)) $res[] = $data;
                    @oci_free_statement($stat);
                    if (isset($this->error_code['query']) && $this->error_code['query']) unset($this->error_code['query']);
                    $this->stat = [];
                    foreach ($this->sql_param as $key => $val) {
                        $n = strtr($key, array(':'=>''));
                        if (isset($$n))$this->stat[$n] = $$n;
                    }
                    if (count($this->stat)) $res = $this->stat;
                }
                else {
                    $this->error_text = $this->getOciErrorMessage($stat);
                    $this->logs[] = $this->error_text;
                    $this->DBError($this->error_text, 'query');
                    $res = false;
                }
                if (isset($this->error_code['curs']) && $this->error_code['curs']) unset($this->error_code['curs']);
            }
            else {
                $this->error_text = 'Oracle cursor output is not available.';
                $this->logs[] = $this->sanitizeErrorMessage($this->error_text);
                $this->DBError($this->error_text, 'curs');
                $res = false;
            }
            if (isset($this->error_code['stat']) && $this->error_code['stat']) unset($this->error_code['stat']);
        }
        else {
            $this->error_text = 'Oracle statement could not be parsed.';
            $this->logs[] = $this->sanitizeErrorMessage($this->error_text);
            $this->DBError($this->error_text, 'stat');
            $res = false;
        }
        if (!$this->oracle_config['p_connect']) $this->closeOracle();
        return $res;
    }

    private function getOciErrorMessage ($statement = null): string {
        $error = $statement ? oci_error($statement) : oci_error($this->oracle);
        if (is_array($error)) return (string) ($error['message'] ?? 'Oracle query failed.');
        return is_string($error) ? $error : 'Oracle query failed.';
    }

    /**
     * Helper function for processing the result of a query to the Oracle database
     * @param $res - object with query result
     * @param int $one - processing type (see getResults function)
     * @return array
     */
    private function res2array ($res, $one) {
        $result = [];
        switch ($one) {
            case 3:
                foreach ($res as $row) {
                    if (sizeof($row) != 1) {
                        $result = $res;
                        break;
                    }
                    $key = join('', array_keys($row));
                    $result[$key][] = $row[$key];
                }
                break;
            case 4:
                foreach ($res as $row) {
                    if (sizeof($row) != 1) {
                        $result = $res;
                        break;
                    }
                    $key = join('', array_keys($row));
                    $result[] = $row[$key];
                }
                break;
            case 5:
                foreach ($res as $row) {
                    if (sizeof($row) != 2) {
                        $result = $res;
                        break;
                    }
                    list($key1, $key2) = array_keys($row);
                    $key = $row[$key1];
                    $result[$key] = $row[$key2];
                }
                break;
            case 6:
                // 'dub_all': like case 5, but a repeated first-column value collects
                // every second-column value into an array instead of the last one
                // silently overwriting the rest - matching MySQL.php/PDOLIB.php.
                $keys = [];
                $idx = 0;
                foreach ($res as $row) {
                    if (sizeof($row) != 2) {
                        $result = $res;
                        break;
                    }
                    list($key1, $key2) = array_keys($row);
                    $key = $row[$key1];
                    if (!$key) $key = 'no_value_'.$idx;
                    $val = $row[$key2];
                    if (!in_array($key, $keys, true)) {
                        $keys[] = $key;
                        $result[$key] = $val;
                    }
                    else {
                        if (is_array($result[$key])) {
                            if (!in_array($val, $result[$key])) $result[$key][] = $val;
                        }
                        else {
                            $prev = $result[$key];
                            if ($prev != $val) {
                                $result[$key] = [];
                                $result[$key][] = $prev;
                                $result[$key][] = $val;
                            }
                        }
                    }
                    $idx++;
                }
                break;
            default:
                $result = $res;
        }
        return $result;
    }

    /**
     * Connection close
     * @return void
     */
    private function closeOracle () {
        if ($this->oracle) oci_close($this->oracle);
        $this->status = false;
    }

    /** Escape a value for an Oracle SQL string literal. @param string $string */
    private function escapeString ($string) {
        return str_replace("'", "''", (string) $string);
    }

    /**
     * Format a PHP value as an Oracle SQL literal.
     * @param mixed $value
     * @return string
     */
    private function formatSqlValue ($value): string {
        if ($value === null || $value === 'NULL') return 'NULL';
        return "'".$this->escapeString($value)."'";
    }

    /**
     * Build a WHERE clause from fields that exist in the table.
     * @param mixed $index
     * @param array $tableFields
     * @return string|false
     */
    private function buildWhereClause ($index, array $tableFields) {
        if (!$index) return '';
        if (!is_array($index)) {
            $this->error_text = "Could not create WHERE query: Error keys - $index (not array)";
            $this->logs[] = $this->error_text;
            return false;
        }

        $conditions = [];
        foreach ($index as $key => $value) {
            if (!in_array($key, $tableFields, true)) continue;
            if ($value === 'NULL') $conditions[] = "$key IS NULL";
            elseif ($value === 'NOT NULL') $conditions[] = "$key IS NOT NULL";
            else $conditions[] = "$key = ".$this->formatSqlValue($value);
        }

        return $conditions ? 'WHERE '.implode(' AND ', $conditions) : '';
    }

    /**
     * Getting a list of table fields in Oracle DB
     * @param string $table - table name
     * @param int $all - all tables (>0) or only user tables (=0)
     * @return array|mixed
     */
    public function getListFields ($table, $all = 0) {
        $table = $this->validateIdentifier((string) $table);
        $name_field = [];
        $table_key = strtoupper($table);
        if (!isset($this->db_TableListOracle[$table_key])) {
            $tables = $this->getTableList($all);
            if (!is_array($tables)) return false;
            $existing_tables = array_map('strtoupper', $tables);
            if (in_array($table_key, $existing_tables, true)) {
                $quoted_table = "'".$this->escapeString($table_key)."'";
                if ($all) $sql = "SELECT column_name FROM all_tab_cols WHERE table_name = $quoted_table";
                else $sql = "SELECT column_name FROM user_tab_cols WHERE table_name = $quoted_table";
                $fields = $this->getResults($sql, 4);
                foreach ($fields as $key => $value) $name_field[] = $value;
                $this->db_TableListOracle[$table_key] = $name_field;
            }
            else {
                $this->error_text = "No table '$table' found in data base";
                $this->logs[] = $this->error_text;
                return false;
            }
        }
        else {
            reset($this->db_TableListOracle[$table_key]);
            $name_field = $this->db_TableListOracle[$table_key];
        }
        return $name_field;
    }

    /**
     * Getting a list of tables in Oracle DB
     * @param int $all - all tables (>0) or only user tables (=0)
     * @return mixed
     */
    public function getTableList ($all = 0): array|false {
        if ($all) $sql = "SELECT table_name FROM all_tables";
        else $sql = "SELECT table_name FROM user_tables";
        return $this->getResults($sql, 4);
    }

    /**
     * Creating an Insert query to Oracle DB
     *
     * @param string $table - table name
     * @param array $values - array of data to add in the format array(['field_name'] => 'value');
     * @return string
     */
    public function getInsertSQL (string $table, array $values): string|false {
        $table = $this->validateIdentifier($table);
        if (!$tab_fields = $this->getListFields($table)) return FALSE;
        if (!is_array($values)) {
            $this->error_text = "Could not create update query: Error values - $values (not array)";
            $this->logs[] = $this->error_text;
            return FALSE;
        }
        $fields = '';
        $val = '';
        foreach ($values as $key => $value) {
            if (in_array($key,$tab_fields)) {
                $fields = ($fields)?"$fields, $key":"$key";
                $value_sql = $this->formatSqlValue($value);
                $val = ($val)?"$val, $value_sql":"$value_sql";
            }
        }
        return "INSERT INTO $table ($fields) VALUES ($val)";
    }

    /**
     * Creating an Update query to Oracle DB
     *
     * @param string $table - table name
     * @param array $values - array of data for update in the format array(['field_name'] => 'value');
     * @param mixed $index - array of WHERE condition data in the format array(['field_name'] => 'value');
     * @return string
     */
    public function getUpdateSQL (string $table, array $values, array|false $index=FALSE): string|false {
        $table = $this->validateIdentifier($table);
        if (!$tab_fields = $this->getListFields($table)) {
            return FALSE;
        }
        if (!is_array($values)) {
            $this->error_text = "Could not create update query: Error values - $values (not array)";
            $this->logs[] = $this->error_text;
            return FALSE;
        }
        $fields = '';
        foreach ($values as $key => $value) {
            if (in_array($key,$tab_fields)) {
                $value_sql = $this->formatSqlValue($value);
                $fields = ($fields)?"$fields, $key = $value_sql":"$key = $value_sql";
            }
        }
        $ind = $this->buildWhereClause($index, $tab_fields);
        if ($ind === false) return false;
        return "UPDATE $table SET $fields $ind";
    }

    /**
     * Creating a Delete query to Oracle DB
     * @param string $table - table name
     * @param mixed $index - array of WHERE condition data in the format array(['field_name'] => 'value');
     * @return string
     */
    public function getDeleteSQL (string $table, array|false $index=false): string|false {
        $table = $this->validateIdentifier($table);
        if (!$tab_fields = $this->getListFields($table)) return false;
        $ind = $this->buildWhereClause($index, $tab_fields);
        if ($ind === false) return false;
        return "DELETE FROM $table $ind";
    }

    /**
     * Error handling.
     * Output to screen, save to error variable.
     * @param string $message - error message
     * @param string $code - error code
     * @return bool
     */
    private function DBError ($message=false, $code = '') {
        if (!$message) $message = $this->error_text;
        if ($code && isset($this->error_code[$code])) return false;
        elseif ($code) $this->error_code[$code] = true;
        parent::Error((string) $message, 'Oracle');
        if ($this->error_exit && !$this->oracle_config['p_connect']) $this->closeOracle();
        return false;
    }
}