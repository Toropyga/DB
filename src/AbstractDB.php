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
     * Коды существующих ошибок
     * @var array
     */
    protected $error_code = array();

    private function __construct() {
        if (!defined("WWW_PATH")) {
            /**
             * Путь к серверу в браузере вида http://domain_name
             */
            define("WWW_PATH", $_SERVER['SERVER_NAME']);
        }
    }
    protected function Error ($message=false, $code = '') {

        $server_ip = (isset($_SERVER['REMOTE_ADDR']))?$_SERVER['REMOTE_ADDR']:'';
        if (!$server_ip) $server_ip = urldecode(getenv('HTTP_CLIENT_IP'));

        $ref = (isset($_SERVER['HTTP_REFERER']))?$_SERVER['HTTP_REFERER']:'-';
        $err = "Critical Database Error from DBOracle (".WWW_PATH.") \nLink error: ".$_SERVER['REQUEST_URI']."\nReferer: ".$ref."\nServer IP: ".$server_ip."\n".$message;
        $this->logs[] = preg_replace("/\n/", ' :: ', $err);
        $error = '<br><span style="color: #FF0000"><b>Critical Database Error ('.WWW_PATH.') '.date('d-m-Y H:i:s').'</b></span><br>';
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
}