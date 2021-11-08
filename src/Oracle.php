<?php

/**
 * Класс для работы с БД Oracle
 * @author FYN
 * Date: 12/03/2009
 * @version 3.1.4
 * @copyright 2009-2021
 */

namespace FYN\DB;

class Oracle extends AbstractDB {
    /**
     * Подключение к Oracle
     * @var mixed
     */
    private $oracle;
    /**
     * Список существующих таблиц в БД Oracle и их полей
     * @var array
     */
    private $db_TableListOracle = array();
    /**
     * Конфигурация подключения к Oracle
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
     * Записывать в лог все действия (true) или только ошибки (false)
     * @var bool
     */
    private $log_all = true;
    /**
     * Текст ошибки в логе
     * @var string
     */
    private $error_text = '';
    /**
     * Статус подключения к БД
     * @var bool
     */
    public $status = false;
    /**
     * Запросы выполняются к пакету
     * @var bool
     */
    private $package = true;
    /**
     * Выполнять запрос и ждать возврата курсора
     * @var bool
     */
    private $cursor = true;
    /**
     * массив переменных запроса
     * @var array
     */
    private $sql_param = array();
    /**
     * Результат
     * @var array
     */
    private $stat = array();

    /**
     * Версия клиента
     * @var string
     */
    public $client_version = '';

    /**
     * Версия сервера
     * @var string
     */
    public $server_version = '';

    /**
     * Типы полей в БД.
     * Используются при задании типа данных переменной в запросе.
     * @var array
     */
    private $types = array(SQLT_BFILEE, OCI_B_BFILE, SQLT_CFILEE, OCI_B_CFILEE, SQLT_CLOB, OCI_B_CLOB, SQLT_BLOB, OCI_B_BLOB, SQLT_RDD, OCI_B_ROWID, SQLT_NTY, OCI_B_NTY, SQLT_INT, OCI_B_INT, SQLT_CHR, SQLT_BIN, OCI_B_BIN, SQLT_LNG, SQLT_LBI, SQLT_RSET);

    /**
     * DBOracle constructor.
     * @param string $HOST - сервер
     * @param string $NAME - имя базы данных
     * @param string $USER - пользователь
     * @param string $PASS - пароль
     * @param int $USE_HOST - какая строка подклюения используется (принимает значение 0, 1 или 2) оптимально 2
     * @param string $PORT - порт
     * @param bool $P_CONNECT - использовать ли постоянное подключение
     * @param string $CHARSET - кодировка
     * @param bool $no_connect - не подключаться к БД при инициации класса
     */
    public function __construct ($HOST=NULL, $NAME=NULL, $USER=NULL, $PASS=NULL, $USE_HOST=NULL, $PORT=NULL, $P_CONNECT=NULL, $CHARSET = '', $no_connect = false) {
        if (defined('DB_ORACLE_HOST') && !$HOST) $this->oracle_config['host'] = DB_ORACLE_HOST; elseif ($HOST) $this->oracle_config['host'] = $HOST;
        if (defined('DB_ORACLE_PORT') && !$PORT) $this->oracle_config['port'] = DB_ORACLE_PORT; elseif ($PORT) $this->oracle_config['port'] = $PORT;
        if (defined('DB_ORACLE_NAME') && !$NAME) $this->oracle_config['name'] = DB_ORACLE_NAME; elseif ($NAME) $this->oracle_config['name'] = $NAME;
        if (defined('DB_ORACLE_USER') && !$USER) $this->oracle_config['user'] = DB_ORACLE_USER; elseif ($USER) $this->oracle_config['user'] = $USER;
        if (defined('DB_ORACLE_PASS') && !$PASS) $this->oracle_config['pass'] = DB_ORACLE_PASS; elseif ($PASS) $this->oracle_config['pass'] = $PASS;
        if (defined('DB_ORACLE_STORAGE') && ($P_CONNECT !== false || $P_CONNECT !== true)) $this->oracle_config['p_connect'] = DB_ORACLE_STORAGE;
        elseif ($P_CONNECT && ($P_CONNECT === false || $P_CONNECT === true)) $this->oracle_config['p_connect'] = $P_CONNECT;
        if (defined('DB_ORACLE_CHARSET') && !$CHARSET) $this->oracle_config['charset'] = DB_ORACLE_CHARSET; elseif ($CHARSET) $this->oracle_config['charset'] = $CHARSET;

        if (defined('DB_ORACLE_DEBUG')) $this->debug = DB_ORACLE_DEBUG;
        if (defined('DB_ORACLE_ERROR_EXIT')) $this->error_exit = DB_ORACLE_ERROR_EXIT;
        if (defined('DB_ORACLE_LOG_NAME')) $this->log_file = DB_ORACLE_LOG_NAME;
        if (defined('DB_ORACLE_LOG_ALL')) $this->log_all = DB_ORACLE_LOG_ALL;

        if (defined('DB_ORACLE_USE_HOST') && !in_array($USE_HOST, array(0,1,2))) $USE_HOST = DB_ORACLE_USE_HOST;
        if ($USE_HOST == 2 || $USE_HOST == 1) $this->oracle_config['use_host'] = $USE_HOST;
        if (defined("SQLT_BOL")) {
            $this->types[] = SQLT_BOL;
            $this->types[] = OCI_B_BOL;
        }
        $this->client_version = oci_client_version();

        if (!$no_connect) $this->getOracle();
    }

    /**
     * Запись в лог
     * Деструктор класса.
     */
    public function __destruct() {
        $this->status = false;
    }

    /**
     * Отправка SQL-запроса к БД Oracle и возврат результата запроса
     *
     * @param string $sql - SQL-запрос
     * @param int $one - параметр ответа
     * Принимает значения:
     *      0 или '' - (выборка: любое количество строк и столбцов) ожидаем массив ассоциативных массивов ([] => array(имя_поля => значение));
     *      1 - (выборка: одна строка / один столбец) ожидаем строку, если при выборке получилось более одного столбца - возвращает ассоциативный массив (имя_поля => значение), если более одной строки - возвращает массив значений ([] => значение), если более одной строки и более одного столбца - массив ассоциативных массивов ([] => array(имя_поля => значение));
     *      2 - (выборка: одна строка / множество столбцов) ожидаем ассоциативный массив (имя_поля => значение), если более одной строки и один столбец - возвращает массив значений ([] => значение), если более одной строки и более одного столбца - массив ассоциативных массивов ([] => array(имя_поля => значение));
     *      3 - (выборка: множество строк / один столбец) ожидаем ассоциативный массив массивов (имя_поля => array([] => значение), если более одной строки и более одного столбца - массив ассоциативных массивов ([] => array(имя_поля => значение));
     *      4 - (выборка: множество строк / один столбец) ожидаем массив значений ([] => значение), если более одной строки и более одного столбца - массив ассоциативных массивов ([] => array(имя_поля => значение)).
     *      5 - (выборка: множество строк / 2 столбца) ожидаем массив значений ([значение поля 1] => значение поля 2)
     *  строковые (аналог числовых):
     *      'all' или '' - (выборка: любое количество строк и столбцов) ожидаем массив ассоциативных массивов ([] => array(имя_поля => значение));
     *      'one' - (выборка: одна строка / один столбец) ожидаем строку, если при выборке получилось более одного столбца - возвращает ассоциативный массив (имя_поля => значение), если более одной строки - возвращает массив значений ([] => значение), если более одной строки и более одного столбца - массив ассоциативных массивов ([] => array(имя_поля => значение));
     *      'row' - (выборка: одна строка / множество столбцов) ожидаем ассоциативный массив (имя_поля => значение), если более одной строки и один столбец - возвращает массив значений ([] => значение), если более одной строки и более одного столбца - массив ассоциативных массивов ([] => array(имя_поля => значение));
     *      'column' - (выборка: множество строк / один столбец) ожидаем ассоциативный массив массивов (имя_поля => array([] => значение), если более одной строки и более одного столбца - массив ассоциативных массивов ([] => array(имя_поля => значение));
     *      'col' - (выборка: множество строк / один столбец) ожидаем массив значений ([] => значение), если более одной строки и более одного столбца - массив ассоциативных массивов ([] => array(имя_поля => значение)).
     *      'dub' - (выборка: множество строк / 2 столбца) ожидаем массив значений ([значение поля 1] => значение поля 2)
     * @return mixed SQL query result
     */
    public function getResults ($sql, $one=0) {
        if (!$this->status) {
            if ($this->oracle_config['p_connect']) return false;
            else $this->getOracle();
        }
        $res = $this->query($sql);
        $one = parent::checkReturnType($one);
        if ($one === false) {
            $this->logs[] = "Wrong parameter ONE: ".$one;
            $one = 0;
        }
        if ($one == 1) $result = '';
        else $result = array();
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
     * Подключение к БД Oracle
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
            $this->logs[] = $this->error_text."Connect Error: ".$e['message']." :: SERVER: ".$this->oracle_config['host'];
            $this->DB_Error($this->error_text."Connect Error: ".$e['message']." :: SERVER: ".$this->oracle_config['host'], 'getOracle');
            $this->status = false;
            return false;
        }
        elseif (isset($this->error_code['getOracle']) && $this->error_code['getOracle']) unset($this->error_code['getOracle']);
        $this->status = true;
        $this->server_version = oci_server_version($this->oracle);
        return true;
    }

    /**
     * Формирование запроса к пакету Oracle с возвратом в переменную перед запросом
     * @param $package - имя пакета
     * @param $procedure - имя процедуры
     * @param string $query_args - строка аргументов, через запятую
     * @return string
     */
    public function getPackageQuery($package, $procedure, $query_args = '') {
        if ($package) return " Begin :res := ".$package.".".$procedure."(".$query_args."); End;";
        else return " Begin :res := ".$procedure."(".$query_args."); End;";
    }

    /**
     * Формирование запроса к пакету Oracle с возвратом в переменную в запросе
     * @param $package
     * @param $procedure
     * @param string $query_args
     * @return string
     */
    public function getProcedureQuery($package, $procedure, $query_args = '') {
        if ($package) return " Begin ".$package.".".$procedure."(".$query_args."); End;";
        else return " Begin ".$procedure."(".$query_args."); End;";
    }

    /**
     * Установка переменных.
     * На вход принимается массив двух видов:
     *      1. array(':key' => 'value'),
     *          где ':key' - имя переменной, 'value' - значение
     *      2. array(':key' => array('length' => '12345', 'type' => OCI_B_INT, 'value' => '555555')),
     *          где ':key' - имя переменной,
     *              внутренний массив содержит поля:
     *                  'length' - максимальный размер данных (-1 - текущий размер значения),
     *                  'type' - тип данных, к которому Oracle будет приводить значения (см. ниже),
     *                  'value' - значение
     * Допустимые значения типов (https://www.php.net/manual/ru/function.oci-bind-by-name.php):
     *    SQLT_BFILEE или OCI_B_BFILE - для BFILE-объектов;
     *    SQLT_CFILEE или OCI_B_CFILEE - для CFILE-объектов;
     *    SQLT_CLOB или OCI_B_CLOB - для CLOB-объектов;
     *    SQLT_BLOB или OCI_B_BLOB - для BLOB-объектов;
     *    SQLT_RDD или OCI_B_ROWID - для ROWID-объектов;
     *    SQLT_NTY или OCI_B_NTY - для именованных типов даты;
     *    SQLT_INT или OCI_B_INT - для целых чисел;
     *    SQLT_CHR - для символов VARCHAR;
     *    SQLT_BIN или OCI_B_BIN - для RAW-полей;
     *    SQLT_LNG - для LONG-полей;
     *    SQLT_LBI - для LONG RAW полей;
     *    SQLT_RSET - для курсоров, созданных функцией oci_new_cursor();
     *    // SQLT_BOL или OCI_B_BOL - для PL/SQL BOOLEAN
     * @param array $bind
     */
    public function setBind ($bind = array()) {
        $this->sql_param = $bind;
    }

    /**
     * Установка параметра запросов
     * @param bool $package - параметр запросов true - запрос к пакету, false - прямой запрос
     */
    public function setPackage ($package = true) {
        if ($package) $this->package = true;
        else $this->package = false;
    }

    /**
     * Установить параметр работы с запросами
     * @param bool $cursor - true - запросы возвращают курсор, false - простой запрос без курсора
     */
    public function setCursor ($cursor = true) {
        $this->cursor = $cursor;
    }

    /**
     * Выполнение запроса к БД Oracle
     * @param $sql - запрос
     * @return array|false
     */
    public function query ($sql) {
        if (!$this->status) return false;
        if ($this->debug) $this->logs[] = "SQL = ". $sql;
        $this->error = false;
        $stat = oci_parse($this->oracle, $sql);
        $replace = array();
        foreach ($this->sql_param as $key => $val) {
            if (preg_match("/$key/", $sql)) {
                $n = strtr($key, array(':'=>''));
                $max_length = 4096;
                $type =  SQLT_CHR;
                $no_replace = false;
                if (is_array($val)) {
                    if (isset($val['length'])) $max_length = $val['length']*1;
                    if (isset($val['type']) && in_array($type, $this->types)) $type = $val['type'];
                    if (!isset($val['value'])) $val['value'] = '';
                    if (in_array($type, array(SQLT_BFILEE, OCI_B_BFILE, SQLT_CFILEE, OCI_B_CFILEE, SQLT_CLOB, OCI_B_CLOB, SQLT_BLOB, OCI_B_BLOB, SQLT_RDD, OCI_B_ROWID))) {
                        if (in_array($type, array(SQLT_BFILEE, OCI_B_BFILE, SQLT_CFILEE, OCI_B_CFILEE))) $$n = oci_new_descriptor($this->oracle, OCI_D_FILE);
                        elseif (in_array($type, array(SQLT_CLOB, OCI_B_CLOB, SQLT_BLOB, OCI_B_BLOB))) $$n = oci_new_descriptor($this->oracle, OCI_D_LOB);
                        else $$n = oci_new_descriptor($this->oracle, OCI_D_ROWID);
                        $no_replace = true;
                        $max_length = -1;
                    }
                    else $$n = $val['value'];
                }
                else $$n = $val;
                if ($no_replace) {
                    $replace[$key] = '';
                    if ($this->debug) $this->logs[] = "Bind: key = ". $key .", value = 'LOB', length = ". $max_length .", type = ". $type;
                }
                else {
                    $replace[$key] = $$n;
                    if ($this->debug) $this->logs[] = "Bind: key = ". $key .", value = ". $$n .", length = ". $max_length .", type = ". $type;
                }
                oci_bind_by_name($stat, $key, $$n, $max_length, $type);
            }
        }
        $curs = false;
        if ($this->cursor) $curs = oci_new_cursor($this->oracle);
        if (!isset($this->sql_param[':res']) && preg_match("/:res\s?(\W)/", $sql)) oci_bind_by_name($stat, ":res", $curs, -1, SQLT_RSET);
        if ($stat) {
            if ($this->cursor && $curs) {
                $run_time = time();
                if (@oci_execute($stat) && @oci_execute($curs)) {
                    $res = array();
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
                    $this->stat = array();
                    foreach ($this->sql_param as $key => $val) {
                        $n = strtr($key, array(':'=>''));
                        if (isset($$n))$this->stat[$n] = $$n;
                    }
                    if (count($this->stat)) $res = $this->stat;
                }
                else {
                    if (is_array(oci_error($stat))) {
                        $sql_print = strtr(join(' :: ', oci_error($stat)), $replace);
                        $this->logs[] = $this->error_text . $sql_print;
                        $this->DB_Error($this->error_text . $sql_print, 'query');
                    }
                    elseif (is_string(oci_error($stat))) {
                        $sql_print = strtr(oci_error($stat), $replace);
                        $this->logs[] = $this->error_text . $sql_print;
                        $this->DB_Error($this->error_text . $sql_print, 'query');
                    }
                    $res = false;
                }
                $this->run_time = time()-$run_time;
                if (isset($this->error_code['curs']) && $this->error_code['curs']) unset($this->error_code['curs']);
            }
            elseif (!$this->cursor) {
                if (@oci_execute($stat)) {
                    $res = array();
                    while ($data = oci_fetch_array($stat, OCI_ASSOC + OCI_RETURN_NULLS)) $res[] = $data;
                    @oci_free_statement($stat);
                    if (isset($this->error_code['query']) && $this->error_code['query']) unset($this->error_code['query']);
                    $this->stat = array();
                    foreach ($this->sql_param as $key => $val) {
                        $n = strtr($key, array(':'=>''));
                        if (isset($$n))$this->stat[$n] = $$n;
                    }
                    if (count($this->stat)) $res = $this->stat;
                }
                else {
                    if (is_array(oci_error($stat))) {
                        $sql_print = strtr(join(' :: ', oci_error($stat)), $replace);
                        $this->logs[] = $this->error_text . $sql_print;
                        $this->DB_Error($this->error_text . $sql_print, 'query');
                    }
                    elseif (is_string(oci_error($stat))) {
                        $sql_print = strtr(oci_error($stat), $replace);
                        $this->logs[] = $this->error_text . $sql_print;
                        $this->DB_Error($this->error_text . $sql_print, 'query');
                    }
                    $res = false;
                }
                if (isset($this->error_code['curs']) && $this->error_code['curs']) unset($this->error_code['curs']);
            }
            else {
                $sql_print = strtr($sql, $replace);
                $this->logs[] = $this->error_text.'ERROR $curs '."$sql_print";
                $this->DB_Error($this->error_text.'ERROR $curs '."$sql_print", 'curs');
                $res = false;
            }
            if (isset($this->error_code['stat']) && $this->error_code['stat']) unset($this->error_code['stat']);
        }
        else {
            $sql_print = strtr($sql, $replace);
            $this->logs[] = $this->error_text.'ERROR $stat '."$sql_print";
            $this->DB_Error($this->error_text.'ERROR $stat '."$sql_print", 'stat');
            $res = false;
        }
        if (!$this->oracle_config['p_connect']) $this->closeOracle();
        return $res;
    }

    /**
     * Вспомогательная функция для обработки результата запроса к БД Oracle
     * @param $res - объект, возвращаемый функцией mysqli_query
     * @param int $one - тип обработки (см. функцию getResults)
     * @return array
     */
    private function res2array ($res, $one) {
        $result = array();
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
            default:
                $result = $res;
        }
        return $result;
    }

    /**
     * Отключение от БД Oracle
     * @return void
     */
    private function closeOracle () {
        if ($this->oracle) oci_close($this->oracle);
        $this->status = false;
    }

    /**
     * Получение списка полей таблицы в Oracle
     * @param string $table - имя таблицы
     * @param int $all - все таблицы (>0) или только пользовательские (=0)
     * @return array|mixed
     */
    private function getListFields ($table, $all = 0) { // Get Filds from table
        $name_field = array();
        if (!isset($this->db_TableListOracle[$table])) {
            if (in_array($table, $this->getTableList())) {
                if ($all) $sql = "SELECT column_name FROM all_tab_cols WHERE table_name = '$table'";
                else $sql = "SELECT column_name FROM user_tab_cols WHERE table_name = '$table'";
                $fields = $this->getResults($sql, 4);
                foreach ($fields as $key => $value) $name_field[] = $value['Field'];
                $this->db_TableListOracle[$table] = $name_field;
            }
            else {
                $this->logs[] = $this->error_text."No table '$table' found in data base";
                return false;
            }
        }
        else {
            reset($this->db_TableListOracle[$table]);
            $name_field = $this->db_TableListOracle[$table];
        }
        return $name_field;
    }

    /**
     * Получение списка таблиц в базе данных Oracle
     * @param int $all - все таблицы (>0) или только пользовательские (=0)
     * @return mixed
     */
    private function getTableList ($all = 0) {
        if ($all) $sql = "SELECT table_name FROM all_tables";
        else $sql = "SELECT table_name FROM user_tables";
        return $this->getResults($sql, 4);
    }

    /**
     * Создание Insert запроса к Oracle
     *
     * @param string $table - имя таблицы
     * @param array $values - массив данных для добавления в формате array(['имя_поля'] => 'значение');
     * @return string
     */
    public function getInsertSQL ($table, $values) { // Create Insert query
        if (!$tab_fields = $this->getListFields($table)) return FALSE;
        if (!is_array($values)) {
            $this->logs[] = $this->error_text."Could not create update query: Error values - $values (not array)";
            return FALSE;
        }
        $fields = '';
        $val = '';
        foreach ($values as $key => $value) {
            if (in_array($key,$tab_fields)) {
                $fields = ($fields)?"$fields, $key":"$key";
                $value = strtr($value, array("'"=>"\'"));
                $val = ($val)?"$val, '$value'":"'$value'";
            }
        }
        return "INSERT INTO $table ($fields) VALUES ($val)";
    }

    /**
     * Создание Update запроса к Oracle
     *
     * @param string $table - имя таблицы
     * @param array $values - массив данных для обновления в формате array(['имя_поля'] => 'значение');
     * @param mixed $index - массив данных условия WHERE в формате array(['имя_поля'] => 'значение');
     * @return string
     */
    public function getUpdateSQL ($table, $values, $index=FALSE) { // Create Update query
        if (!$tab_fields = $this->getListFields($table)) {
            return FALSE;
        }
        if (!is_array($values)) {
            $this->logs[] = $this->error_text."Could not create update query: Error values - $values (not array)";
            return FALSE;
        }
        $fields = '';
        foreach ($values as $key => $value) {
            if (in_array($key,$tab_fields)) {
                $value = strtr($value, array("'"=>"\'"));
                $fields = ($fields)?"$fields, $key = '$value'":"$key = '$value'";
            }
        }
        if ($index) {
            if (!is_array($index)) {
                $this->logs[] = $this->error_text."Could not create update query: Error keys - $index (not array)";
                return FALSE;
            }
            $ind = '';
            foreach ($index as $key => $value) {
                if (in_array($key,$tab_fields)) {
                    $ind = ($ind)?"$ind AND $key = '$value'":"$key = '$value'";
                }
            }
        }
        if ($ind) $ind = "WHERE $ind";
        return "UPDATE $table SET $fields $ind";
    }

    /**
     * Обработка ошибок.
     * Вывод на экран, отправка на почту администратору, сохранение в переменную error.
     * @param bool $message - сообщение об ошибке
     * @param string $code - код ошибки
     * @return bool
     */
    private function DB_Error ($message=false, $code = '') {
        if ($code && isset($this->error_code[$code])) return false;
        elseif ($code) $this->error_code[$code] = true;
        parent::Error($message, 'Oracle');
        if ($this->error_exit) {
            if (!$this->oracle_config['p_connect']) $this->closeOracle();
            exit;
        }
        return false;
    }
}