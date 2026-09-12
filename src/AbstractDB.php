<?php
/**
 * DB.
 * @author Yuri Frantsevich
 * @copyright 2005-2026
 */

namespace Toropyga\DB;

abstract class AbstractDB implements DatabaseAdapterInterface {
    /**
     * Логи
     * @var array
     */
    protected $logs = [];
    /**
     * Log file name
     * @var string
     */
    protected $log_file = 'db.log';

    /**
     * Query execution time
    * @var float
     */
    public $run_time = 0.0;

    /**
     * Enable or disable debugging features
     * @var bool
     */
    protected $debug = false;

    /**
     * Terminate the program if an error occurs
     * @var bool
     */
    protected $error_exit = false;

    /**
     * Sign of error
     * @var bool
     */
    public $error = false;

    /**
     * Existing error codes
     * @var array
     */
    protected $error_code = [];

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
    * @return float
     */
    public function getRunTime () {
        return $this->run_time;
    }

    /**
     * Checking the output parameter
     * @param int|string $one - output parameter
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
        if (!is_int($one) || $one > 7 || $one < 0) return false;
        return $one;
    }

    /**
     * Validate and quote a table or column identifier.
     * Values must never be passed through this method; use bound parameters.
     * @param string $identifier
     * @param string $quote
     * @return string
     */
    protected function quoteIdentifier (string $identifier, string $quote = '"'): string {
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_$]*$/', $identifier)) {
            throw new \InvalidArgumentException('Invalid database identifier.');
        }
        return $quote.$identifier.$quote;
    }

    protected function validateIdentifier (string $identifier): string {
        $this->quoteIdentifier($identifier);
        return $identifier;
    }

    /**
     * Remove SQL text from messages before they are retained in logs.
     * @param string $message
     * @return string
     */
    protected function sanitizeErrorMessage (string $message): string {
        $message = preg_replace('/(?:Could not (?:query|prepare):|QUERY:|SQL\s*=).*?(?=\s+ERROR:|$)/is', 'Database operation failed.', $message);
        return trim((string) $message);
    }

    /**
     * Error handling.
     * Save a sanitized message to the internal log and optionally throw.
     * @param string $message - error message
     * @param string $lib_name - class name
     * @return bool
     */
    protected function Error ($message='', $lib_name = 'AbstractDB') {
        $this->error = true;
        $context = $this->buildErrorContext();
        $server = $context['server'];
        $message = $this->sanitizeErrorMessage((string) $message);
        $err = "Database error from $lib_name (".$server.") \nLink error: ".$context['request_uri']."\nReferer: ".$context['referer']."\nServer IP: ".$context['server_ip']."\n".$message;
        $this->logs[] = preg_replace("/\n/", ' :: ', $err);
        if ($this->error_exit) throw new DatabaseException($message ?: 'Database operation failed.');
        return true;
    }

    /**
     * Build request metadata used in error reporting.
     * Keeps HTTP-specific access isolated from the database logic.
     * @return array{server:string, request_uri:string, referer:string, server_ip:string}
     */
    protected function buildErrorContext () {
        $ip = $this->getIP();
        return [
            'server' => $this->getServerName(),
            'request_uri' => $this->getRequestUri(),
            'referer' => $this->getReferer(),
            'server_ip' => implode('/', $ip),
        ];
    }

    /**
     * Return a safe server name for CLI/test contexts.
     * @return string
     */
    protected function getServerName () {
        return $_SERVER['SERVER_NAME'] ?? 'CLI';
    }

    /**
     * Return a safe request URI for CLI/test contexts.
     * @return string
     */
    protected function getRequestUri () {
        return $_SERVER['REQUEST_URI'] ?? '-';
    }

    /**
     * Return HTTP Referer when present.
     * @return string
     */
    protected function getReferer () {
        return $_SERVER['HTTP_REFERER'] ?? '-';
    }

    /**
     * Logs return
     * @param string $type - тType of returned data: all - all (default), log - array of logs, file - name of log file, last - last line of logs
     * @return array|string|null
     */
    public function getLogs ($type = 'all') {
        if ($type == 'log') return $this->logs;
        elseif ($type == 'file') return $this->log_file;
        elseif ($type == 'last') return $this->logs ? $this->logs[array_key_last($this->logs)] : null;
        $return['log'] = $this->logs;
        $return['file'] = $this->log_file;
        return $return;
    }

    /**
     * Returns the error code
     * @return array
     */
    public function getErrorCode () {
        return $this->error_code;
    }

    /**
     * Determining the IP address from which the page is opened
     * @return array
     */
    private function getIP () {
        $ipn = $_SERVER['REMOTE_ADDR'] ?? '';
        if (!$ipn) $ipn = urldecode((string) getenv('HTTP_CLIENT_IP'));
        $forwardedFor = getenv('HTTP_X_FORWARDED_FOR');
        $forwarded = getenv('HTTP_X_FORWARDED');
        $forwardedForAlt = getenv('HTTP_FORWARDED_FOR');
        $forwardedAlt = getenv('HTTP_FORWARDED');
        if ($forwardedFor && strcasecmp((string) $forwardedFor, 'unknown')) $strIP = $forwardedFor;
        elseif ($forwarded && strcasecmp((string) $forwarded, 'unknown')) $strIP = $forwarded;
        elseif ($forwardedForAlt && strcasecmp((string) $forwardedForAlt, 'unknown')) $strIP = $forwardedForAlt;
        elseif ($forwardedAlt && strcasecmp((string) $forwardedAlt, 'unknown')) $strIP = $forwardedAlt;
        else $strIP = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        if ($ipn == '::1') $ipn = '127.0.0.1';
        if ($strIP == '::1') $strIP = '127.0.0.1';
        $strIP = trim(explode(',', (string) $strIP, 2)[0]);
        // Keep private and loopback addresses: they are useful in local and
        // containerized deployments.
        $ipn = filter_var($ipn, FILTER_VALIDATE_IP) ?: '';
        $strIP = filter_var($strIP, FILTER_VALIDATE_IP) ?: $ipn;
        $ip = [];
        if ($strIP) {
            if ($strIP != $ipn) {
                $ip['proxy'] = $ipn;
                $ip['ip'] = $strIP;
            }
            else {
                $ip['proxy'] = '';
                $ip['ip'] = $ipn;
            }
        }
        else {
            $ip['proxy'] = '';
            $ip['ip'] = '';
        }
        return $ip;
    }
}
