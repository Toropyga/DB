<?php

declare(strict_types=1);

/**
 * Class for working with PostgreSQL database
 * @author Yuri Frantsevich
 * @copyright 2026
 */

namespace Toropyga\DB;

/**
 * PostgreSQL adapter implemented with the native ext-pgsql extension.
 *
 * SQL values are sent through pg_query_params() whenever a parameterized
 * method is used. Table and column names are validated before interpolation.
 */
class PostgreSQL extends AbstractDB
{
    /** Connection settings and adapter state. */
    private string $db_host = '127.0.0.1';
    private int|string $db_port = 5432;
    private string $db_name = '';
    private string $db_user = '';
    private string $db_pass = '';
    private bool $log_all = true;
    private mixed $db_connect = null;
    private bool $db_storage = true;
    private array $db_TableList = [];
    private array $db_Tables = [];

    public bool $status = false;

    /**
     * Create a PostgreSQL adapter.
     *
     * Explicit arguments take precedence over DB_PGSQL_* constants. A
     * non-persistent connection is opened lazily on the first query.
     */
    public function __construct($HOST = false, $PORT = false, $NAME = false, $USER = false, $PASS = false, array $options = [])
    {
        $this->db_host = defined('\DB_PGSQL_HOST') && !$HOST ? (string) \DB_PGSQL_HOST : (string) ($HOST ?: $this->db_host);
        $this->db_port = defined('\DB_PGSQL_PORT') && !$PORT ? \DB_PGSQL_PORT : ($PORT ?: $this->db_port);
        $this->db_name = defined('\DB_PGSQL_NAME') && !$NAME ? (string) \DB_PGSQL_NAME : (string) $NAME;
        $this->db_user = defined('\DB_PGSQL_USER') && !$USER ? (string) \DB_PGSQL_USER : (string) $USER;
        $this->db_pass = defined('\DB_PGSQL_PASS') && !$PASS ? (string) \DB_PGSQL_PASS : (string) $PASS;
        if (defined('\DB_PGSQL_STORAGE')) $this->db_storage = (bool) \DB_PGSQL_STORAGE;
        if (defined('\DB_PGSQL_DEBUG')) $this->debug = (bool) \DB_PGSQL_DEBUG;
        if (defined('\DB_PGSQL_ERROR_EXIT')) $this->error_exit = (bool) \DB_PGSQL_ERROR_EXIT;
        if (defined('\DB_PGSQL_LOG_NAME')) $this->log_file = (string) \DB_PGSQL_LOG_NAME;
        if (defined('\DB_PGSQL_LOG_ALL')) $this->log_all = (bool) \DB_PGSQL_LOG_ALL;

        if (array_key_exists('storage', $options)) $this->db_storage = (bool) $options['storage'];
        if (array_key_exists('debug', $options)) $this->debug = (bool) $options['debug'];
        if (array_key_exists('error_exit', $options)) $this->error_exit = (bool) $options['error_exit'];
        if (array_key_exists('log_name', $options)) $this->log_file = (string) $options['log_name'];
        if (array_key_exists('log_all', $options)) $this->log_all = (bool) $options['log_all'];
        if (!function_exists('pg_connect')) {
            return $this->dbError('PHP PostgreSQL extension is not installed.', '__construct');
        }
        if ($this->db_storage) $this->getConnect();
    }

    public function __destruct()
    {
        $this->getClose();
    }

    /** Execute a raw SELECT and shape its rows according to $one. */
    public function getResults(string $sql, int|string $one = 0): mixed
    {
        $one = $this->normalizeReturnType($one);
        if ($one === false) $one = 0;
        if ($one === 7) {
            $sql = 'EXPLAIN '.$sql;
            $one = 2;
        }
        $result = $this->query($sql);
        if ($result === false) return $one === 1 ? '' : [];
        $processed = $this->processResult($result, $one);
        $this->closeIfTransient();
        return $processed;
    }

    /** Execute SQL with named parameters and shape the returned rows. */
    public function getQuery(string $sql, array $values = [], int|string $one = 0): mixed
    {
        $one = $this->normalizeReturnType($one);
        if ($one === false) $one = 0;
        if ($one === 7) {
            $sql = 'EXPLAIN '.$sql;
            $one = 2;
        }
        $result = $this->queryPrepared($sql, $values);
        if ($result === false) return $one === 1 ? '' : [];
        $processed = $this->processResult($result, $one);
        $this->closeIfTransient();
        return $processed;
    }

    /** Build SQL text for display only; this method does not execute it. */
    public function getQuerySQL(string $sql, array $values = []): string
    {
        $position = 0;
        return (string) preg_replace_callback('/(?<!:):([A-Za-z_][A-Za-z0-9_]*)/', function (array $match) use ($values, &$position): string {
            $key = $match[1];
            if (!array_key_exists($key, $values)) return $match[0];
            $position++;
            return $this->formatSqlValue($values[$key]);
        }, $sql);
    }

    /** Execute a raw SQL statement through PostgreSQL. */
    public function query(string $sql): mixed
    {
        if (!$this->ensureConnected() || trim($sql) === '') return false;
        $started = microtime(true);
        $result = @pg_query($this->db_connect, $sql);
        $this->run_time = microtime(true) - $started;
        if ($result === false) {
            $this->dbError('Could not query: '.$sql, 'query');
            $this->closeIfTransient();
            return false;
        }
        $this->clearError('query');
        return $result;
    }

    /** Return user tables from the public PostgreSQL schema. */
    public function getTableList(): array|false
    {
        $result = $this->getResults("SELECT tablename FROM pg_catalog.pg_tables WHERE schemaname = 'public' ORDER BY tablename", 4);
        $this->db_Tables = is_array($result) ? $result : [];
        return is_array($result) ? $result : false;
    }

    /** Return cached column names for a validated table. */
    public function getListFields(string $table): array|false
    {
        $table = $this->validateIdentifier($table);
        if (!$this->db_Tables || !in_array($table, $this->db_Tables, true)) $this->getTableList();
        if (!in_array($table, $this->db_Tables, true)) {
            $this->dbError("Table does not exist: $table", 'getListFields');
            return false;
        }
        if (isset($this->db_TableList[$table])) return $this->db_TableList[$table];
        $fields = $this->getResults(
            "SELECT column_name FROM information_schema.columns WHERE table_schema = 'public' AND table_name = ".$this->formatSqlValue($table)." ORDER BY ordinal_position",
            4
        );
        if (!is_array($fields)) return false;
        $this->db_TableList[$table] = $fields;
        return $fields;
    }

    /** Insert a row using PostgreSQL positional parameters. */
    public function setInsert(string $table, array $values): bool
    {
        $table = $this->validateIdentifier($table);
        $fields = $this->validFields($table, $values);
        if (!$fields) return false;
        $params = [];
        $placeholders = [];
        foreach ($fields as $field) {
            $placeholders[] = '$'.(count($params) + 1);
            $params[] = $this->normalizeValue($values[$field]);
        }
        $sql = 'INSERT INTO '.$this->quoteIdentifier($table, '"').' ('.implode(', ', array_map(fn (string $field): string => $this->quoteIdentifier($field, '"'), $fields)).') VALUES ('.implode(', ', $placeholders).')';
        return $this->executeParams($sql, $params);
    }

    /** Build an INSERT statement with quoted SQL literals for inspection. */
    public function getInsertSQL(string $table, array $values): string|false
    {
        $table = $this->validateIdentifier($table);
        $fields = $this->validFields($table, $values);
        if (!$fields) return false;
        $columns = array_map(fn (string $field): string => $this->quoteIdentifier($field, '"'), $fields);
        $literals = array_map(fn (string $field): string => $this->formatSqlValue($values[$field]), $fields);
        return 'INSERT INTO '.$this->quoteIdentifier($table, '"').' ('.implode(', ', $columns).') VALUES ('.implode(', ', $literals).')';
    }

    /** Update a row using bound values and an optional WHERE condition. */
    public function setUpdate(string $table, array $values, array|false $index = false): bool
    {
        $sql = $this->buildUpdateSQL($table, $values, $index, true);
        return $sql === false ? false : $this->executeParams($sql[0], $sql[1]);
    }

    /** Build an UPDATE statement without executing it. */
    public function getUpdateSQL(string $table, array $values, array|false $index = false): string|false
    {
        $sql = $this->buildUpdateSQL($table, $values, $index, false);
        return $sql === false ? false : $sql;
    }

    /** Delete rows using bound values and an optional WHERE condition. */
    public function setDelete(string $table, array|false $index = false): bool
    {
        $built = $this->buildDeleteSQL($table, $index, true);
        return $built === false ? false : $this->executeParams($built[0], $built[1]);
    }

    /** Build a DELETE statement without executing it. */
    public function getDeleteSQL(string $table, array|false $index = false): string|false
    {
        $built = $this->buildDeleteSQL($table, $index, false);
        return $built === false ? false : $built;
    }

    /** Close the active PostgreSQL connection. */
    public function getClose(): bool
    {
        if ($this->db_connect && function_exists('pg_close')) @pg_close($this->db_connect);
        $this->db_connect = null;
        $this->status = false;
        return true;
    }

    /** Return LASTVAL(), or currval() for an explicitly named sequence. */
    public function lastID(string $sequence = ''): string|false
    {
        if (!$this->ensureConnected()) return false;
        if ($sequence !== '') {
            $result = $this->getResults('SELECT currval('.$this->formatSqlValue($sequence).')', 1);
        } else {
            $result = $this->getResults('SELECT LASTVAL()', 1);
        }
        return is_scalar($result) ? (string) $result : false;
    }

    /** Open a connection using a libpq connection string. */
    private function getConnect(): bool
    {
        $connection = sprintf('host=%s port=%s dbname=%s user=%s password=%s', $this->quoteConnectionValue($this->db_host), $this->db_port, $this->quoteConnectionValue($this->db_name), $this->quoteConnectionValue($this->db_user), $this->quoteConnectionValue($this->db_pass));
        $this->db_connect = @pg_connect($connection);
        if (!$this->db_connect) return $this->dbError('Could not connect to PostgreSQL host: '.$this->db_host, 'getConnect');
        $this->status = true;
        return true;
    }

    /** Open a connection on demand when transient mode is enabled. */
    private function ensureConnected(): bool
    {
        return $this->status ? true : $this->getConnect();
    }

    /** Convert named placeholders to PostgreSQL's $1, $2, ... syntax. */
    private function queryPrepared(string $sql, array $values): mixed
    {
        if (!$this->ensureConnected()) return false;
        [$positionalSql, $params] = $this->bindNamedParams($sql, $values);
        return $this->executeRaw($positionalSql, $params);
    }

    /** Execute a parameterized write and close transient connections. */
    private function executeParams(string $sql, array $params): bool
    {
        $result = $this->executeRaw($sql, $params);
        $this->closeIfTransient();
        return $result !== false;
    }

    /** Low-level query execution shared by raw and parameterized methods. */
    private function executeRaw(string $sql, array $params): mixed
    {
        if (!$this->ensureConnected()) return false;
        $started = microtime(true);
        $result = $params ? @pg_query_params($this->db_connect, $sql, $params) : @pg_query($this->db_connect, $sql);
        $this->run_time = microtime(true) - $started;
        if ($result === false) {
            $this->dbError('Could not query: '.$sql, 'query');
            $this->closeIfTransient();
            return false;
        }
        $this->clearError('query');
        return $result;
    }

    /** Convert PgSql\Result rows into the adapter's standard result shapes. */
    private function processResult(mixed $result, int $one): mixed
    {
        if (!is_resource($result) && !is_object($result)) return $result;
        $rows = pg_fetch_all($result, PGSQL_ASSOC) ?: [];
        $columns = $rows ? count($rows[0]) : 0;
        if (!$rows) return $one === 1 ? '' : [];
        if ($one === 1 && $columns === 1) return array_values($rows[0])[0];
        if ($one === 1 && count($rows) === 1) return $rows[0];
        if ($one === 2 && count($rows) === 1) return $rows[0];
        if ($one === 3 && $columns === 1) {
            $key = array_key_first($rows[0]);
            return [$key => array_map(fn (array $row): mixed => $row[$key], $rows)];
        }
        if ($one === 4 && $columns === 1) return array_map(fn (array $row): mixed => array_values($row)[0], $rows);
        if (($one === 5 || $one === 6) && $columns === 2) {
            $result = [];
            foreach ($rows as $row) {
                $values = array_values($row);
                $key = $values[0] ?: 'no_value_'.count($result);
                if ($one === 5) $result[$key] = $values[1];
                elseif (!isset($result[$key])) $result[$key] = $values[1];
                elseif (!is_array($result[$key])) $result[$key] = [$result[$key], $values[1]];
                elseif (!in_array($values[1], $result[$key], true)) $result[$key][] = $values[1];
            }
            return $result;
        }
        return $rows;
    }

    /** Normalize the common result mode names inherited from AbstractDB. */
    private function normalizeReturnType(int|string $one): int|false
    {
        return parent::checkReturnType($one);
    }

    /** Preserve placeholder order, including repeated named parameters. */
    private function bindNamedParams(string $sql, array $values): array
    {
        $params = [];
        $position = 0;
        $sql = preg_replace_callback('/(?<!:):([A-Za-z_][A-Za-z0-9_]*)/', function (array $match) use (&$params, &$position, $values): string {
            $key = $match[1];
            if (!array_key_exists($key, $values)) return $match[0];
            $params[] = $this->normalizeValue($values[$key]);
            return '$'.(++$position);
        }, $sql);
        return [$sql, $params];
    }

    /** Keep only fields that actually exist in the target table. */
    private function validFields(string $table, array $values): array
    {
        $fields = $this->getListFields($table);
        if (!$fields) return [];
        return array_values(array_filter(array_keys($values), fn (mixed $field): bool => is_string($field) && in_array($field, $fields, true)));
    }

    /** Build a parameterized or display-only UPDATE statement. */
    private function buildUpdateSQL(string $table, array $values, array|false $index, bool $parameterized): array|string|false
    {
        $table = $this->validateIdentifier($table);
        $fields = $this->validFields($table, $values);
        if (!$fields || ($index !== false && !is_array($index))) return false;
        $params = [];
        $assignments = [];
        foreach ($fields as $field) {
            $column = $this->quoteIdentifier($field, '"');
            if ($parameterized) {
                $params[] = $this->normalizeValue($values[$field]);
                $assignments[] = $column.' = $'.count($params);
            } else $assignments[] = $column.' = '.$this->formatSqlValue($values[$field]);
        }
        $conditions = $this->buildConditions($table, $index, $params, $parameterized);
        if ($conditions === false) return false;
        $sql = 'UPDATE '.$this->quoteIdentifier($table, '"').' SET '.implode(', ', $assignments).$conditions[0];
        return $parameterized ? [$sql, $conditions[1]] : $sql;
    }

    /** Build a parameterized or display-only DELETE statement. */
    private function buildDeleteSQL(string $table, array|false $index, bool $parameterized): array|string|false
    {
        $table = $this->validateIdentifier($table);
        if ($index !== false && !is_array($index)) return false;
        $params = [];
        $conditions = $this->buildConditions($table, $index, $params, $parameterized);
        if ($conditions === false) return false;
        $sql = 'DELETE FROM '.$this->quoteIdentifier($table, '"').$conditions[0];
        return $parameterized ? [$sql, $conditions[1]] : $sql;
    }

    /** Build a validated WHERE clause and append its bound values. */
    private function buildConditions(string $table, array|false $index, array &$params, bool $parameterized): array|false
    {
        if (!$index) return ['', $params];
        $fields = $this->getListFields($table);
        if (!$fields) return false;
        $conditions = [];
        foreach ($index as $field => $value) {
            if (!is_string($field) || !in_array($field, $fields, true)) continue;
            $column = $this->quoteIdentifier($field, '"');
            if ($value === 'NULL') $conditions[] = $column.' IS NULL';
            elseif ($value === 'NOT NULL') $conditions[] = $column.' IS NOT NULL';
            else {
                if ($parameterized) {
                    $params[] = $this->normalizeValue($value);
                    $conditions[] = $column.' = $'.count($params);
                } else $conditions[] = $column.' = '.$this->formatSqlValue($value);
            }
        }
        return [$conditions ? ' WHERE '.implode(' AND ', $conditions) : '', $params];
    }

    /** Encode array values before sending them to PostgreSQL. */
    private function normalizeValue(mixed $value): mixed
    {
        return is_array($value) ? json_encode($value) : $value;
    }

    /** Format a value as a SQL literal for inspection helpers only. */
    private function formatSqlValue(mixed $value): string
    {
        if ($value === null || $value === 'NULL') return 'NULL';
        return "'".str_replace("'", "''", (string) $this->normalizeValue($value))."'";
    }

    /** Quote values used in the libpq connection string. */
    private function quoteConnectionValue(string $value): string
    {
        return "'".str_replace("'", "\\'", $value)."'";
    }

    /** Close connections created for one operation. */
    private function closeIfTransient(): void
    {
        if (!$this->db_storage) $this->getClose();
    }

    /** Clear a previously recorded error after a successful operation. */
    private function clearError(string $code): void
    {
        if (isset($this->error_code[$code])) unset($this->error_code[$code]);
    }

    /** Record a sanitized adapter error and optionally throw DatabaseException. */
    private function dbError(string $message, string $code = ''): bool
    {
        if ($code && isset($this->error_code[$code])) return false;
        if ($code) $this->error_code[$code] = true;
        $driverError = $this->db_connect ? pg_last_error($this->db_connect) : '';
        parent::Error($message.($driverError ? ' ERROR: '.$driverError : ''), 'PostgreSQL');
        return false;
    }
}
