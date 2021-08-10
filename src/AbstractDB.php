<?php
/**
 * DB.
 * User: Pilgrim
 * Date: 09.08.2021
 * Time: 14:41
 */

namespace FYN\DB;

class AbstractDB {
    /**
     * Логи
     * @var array
     */
    protected $logs = array();
    /**
     * Имя файла в который сохраняется лог
     * @var string
     */
    protected $log_file = 'db.log';

    /**
     * Включить или отключить отладочные функции
     * @var bool
     */
    protected $debug = false; //Show error on Site

    /**
     * Завершить ли работу программы при ошибке
     * @var bool
     */
    protected $error_exit = false; //Exit if script contain error

    /**
     * Признак наличия ошибки
     * @var bool
     */
    public $error = false;

    /**
     * Коды существующих ошибок
     * @var array
     */
    protected $error_code = array();

    /**
     * Включение параметра вывода ошибок
     * @param bool $debug
     */
    public function setDebug ($debug = true) {
        if ($debug) $this->debug = true;
        else $this->debug = false;
    }

    /**
     * Возврат параметра вывода ошибок
     */
    public function getDebug () {
        return $this->debug;
    }

    /**
     * Включение параметра прерывания работы ПО при ошибке
     * @param bool $exit
     */
    public function setErrorExit ($exit = true) {
        if ($exit) $this->error_exit = true;
        else $this->error_exit = false;
    }

    /**
     * Возврат параметра прерывания работы ПО при ошибке
     */
    public function getErrorExit () {
        return $this->error_exit;
    }

    /**
     * Обработка ошибок.
     * Вывод на экран, сохранение в переменную error.
     * @param bool $message - сообщение об ошибке
     * @param string $lib_name - имя библиотеки
     * @return bool
     */
    protected function Error ($message=false, $lib_name = 'AbstractDB') {
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
     * Возвращает логи
     * @return array
     */
    public function getLogs () {
        $return['log'] = $this->logs;
        $return['file'] = $this->log_file;
        return $return;
    }

    /**
     * Определение IP адреса с которого открывается страница
     * @return mixed
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