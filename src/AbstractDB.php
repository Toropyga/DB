<?php
/**
 * DB.
 * @author FYN
 * Date: 09.08.2021
 * @version 1.1.6
 * @copyright 2021-2023
 */

namespace FYN\DB;

class AbstractDB {
    /**
     * Логи
     * @var array
     */
    protected $logs = array();
    /**
     * Log file name
     * @var string
     */
    protected $log_file = 'db.log';

    /**
     * Query execution time
     * @var int
     */
    public $run_time = 0;

    /**
     * Enable or disable debugging features
     * @var bool
     */
    protected $debug = false; //Show error on Site

    /**
     * Terminate the program if an error occurs
     * @var bool
     */
    protected $error_exit = false; //Exit if script contain error

    /**
     * Sign of error
     * @var bool
     */
    public $error = false;

    /**
     * Existing error codes
     * @var array
     */
    protected $error_code = array();

    /**
     * Enabling the error output option
     * @param bool $debug
     */
    public function setDebug ($debug = true) {
        if ($debug) $this->debug = true;
        else $this->debug = false;
    }

    /**
     * Returning the error output parameter
     */
    public function getDebug () {
        return $this->debug;
    }

    /**
     * Enabling the option to interrupt software operation on error
     * @param bool $exit
     */
    public function setErrorExit ($exit = true) {
        if ($exit) $this->error_exit = true;
        else $this->error_exit = false;
    }

    /**
     * Returning the parameter to interrupt the software operation in case of an error
     */
    public function getErrorExit () {
        return $this->error_exit;
    }

    /**
     * Return the execution time of the last SQL query
     * @return int
     */
    public function getRunTime () {
        return $this->run_time;
    }

    /**
     * Checking the output parameter
     * @param $one - output parameter
     * @return false|int
     */
    protected function checkReturnType ($one) {
        if (is_string($one)) {
            if ($one == 'all') $one = 0;
            elseif ($one == 'one') $one = 1;
            elseif ($one == 'row') $one = 2;
            elseif ($one == 'column') $one = 3;
            elseif ($one == 'col') $one = 4;
            elseif ($one == 'dub') $one = 5;
            elseif ($one == 'dub_all') $one = 6;
            elseif ($one == 'explain') $one = 7;
            else return false;
        }
        if ($one > 7 || $one < 0) return false;
        return $one;
    }

    /**
     * Error handling.
     * Output to screen, save to error variable.
     * @param string $message - error message
     * @param string $lib_name - class name
     * @return bool
     */
    protected function Error ($message='', $lib_name = 'AbstractDB') {
        $this->error = true;
        $ip = $this->getIP();
        if (!defined("WWW_PATH")) define("WWW_PATH", $_SERVER['SERVER_NAME']);
        $server_ip = implode("/", $ip);
        $ref = (isset($_SERVER['HTTP_REFERER']))?$_SERVER['HTTP_REFERER']:'-';
        $err = "Critical Database Error from $lib_name (".WWW_PATH.") \nLink error: ".$_SERVER['REQUEST_URI']."\nReferer: ".$ref."\nServer IP: ".$server_ip."\n".$message;
        $this->logs[] = preg_replace("/\n/", ' :: ', $err);
        $error = '<br><span style="color: #FF0000"><b>Critical Database Error from '.$lib_name.' ('.WWW_PATH.') '.date('d-m-Y H:i:s').'</b></span><br>';
        $error .= "<b>Link error:</b> ".$_SERVER['REQUEST_URI']."<br>";
        $error .= "<b>Referer:</b> ".$ref."<br>";
        $error .= "<b>Server IP:</b> ".$server_ip."<br>";
        $error .= '<span style="color: #008000">'.htmlentities($message).':</span> ';
        if ($this->debug) {
            header("Access-Control-Allow-Origin: *");
            header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
            header("Access-Control-Allow-Headers: Origin, Content-Type, X-Auth-Token");
            header("X-XSS-Protection: 1; mode=block");
            header("X-Content-Type-Options: nosniff");
            header("X-Frame-Options: DENY");
            header("Content-Security-Policy: frame-ancestors 'self'");
            header("Content-Type: text/html; charset=utf-8");
            echo $error;
        }
        return true;
    }

    /**
     * Logs return
     * @param string $type - тType of returned data: all - all (default), log - array of logs, file - name of log file, last - last line of logs
     * @return array|string
     */
    public function getLogs ($type = 'all') {
        if ($type == 'log') return $this->logs;
        elseif ($type == 'file') return $this->log_file;
        elseif ($type == 'last') return array_pop($this->logs);
        $return['log'] = $this->logs;
        $return['file'] = $this->log_file;
        return $return;
    }

    /**
     * Determining the IP address from which the page is opened
     * @return array
     */
    private function getIP () {
        $ipn = (isset($_SERVER['REMOTE_ADDR']))?$_SERVER['REMOTE_ADDR']:'';
        if (!$ipn) $ipn = urldecode(getenv('HTTP_CLIENT_IP'));
        if (getenv('HTTP_X_FORWARDED_FOR') && strcasecmp(getenv("HTTP_X_FORWARDED_FOR"), "unknown")) $strIP = getenv('HTTP_X_FORWARDED_FOR');
        elseif (getenv('HTTP_X_FORWARDED') && strcasecmp(getenv("HTTP_X_FORWARDED"), "unknown")) $strIP = getenv('HTTP_X_FORWARDED');
        elseif (getenv('HTTP_FORWARDED_FOR') && strcasecmp(getenv("HTTP_FORWARDED_FOR"), "unknown")) $strIP = getenv('HTTP_FORWARDED_FOR');
        elseif (getenv('HTTP_FORWARDED') && strcasecmp(getenv("HTTP_FORWARDED"), "unknown")) $strIP = getenv('HTTP_FORWARDED');
        else $strIP = (isset($_SERVER['REMOTE_ADDR']))?$_SERVER['REMOTE_ADDR']:'127.0.0.1';
        if ($ipn == '::1') $ipn = '127.0.0.1';
        if ($strIP == '::1') $strIP = '127.0.0.1';
        if (!preg_match("/^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}$/", $ipn)) $ipn = '';
        if (!preg_match("/^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}$/", $strIP)) $strIP = $ipn;
        if ($strIP != $ipn) {
            $ip['proxy'] = $ipn;
            $ip['ip'] = $strIP;
        }
        else {
            $ip['proxy'] = '';
            $ip['ip'] = $ipn;
        }
        return $ip;
    }
}