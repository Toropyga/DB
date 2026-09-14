<?php

declare(strict_types=1);

namespace Toropyga\DB;

use BadMethodCallException;
use InvalidArgumentException;

/**
 * Factory and facade for all supported database adapters.
 *
 * DBAPI::connect() returns the concrete adapter selected by the type string.
 * The instance form additionally proxies adapter method calls through __call().
 */
class DBAPI
{
    public readonly DatabaseAdapterInterface $adapter;

    /**
     * Create a facade for the selected database adapter.
     *
     * @param string $type Connection type, for example mysql, postgresql,
     *                     pdo_mysql, pdo_sqlsrv, pdo_dblib or pdo_firebird.
     * @param array<string, mixed> $parameters Connection parameters.
     */
    public function __construct(string $type, array $parameters = [])
    {
        $this->adapter = self::connect($type, $parameters);
    }

    /**
     * Return the concrete adapter for the requested connection type.
     *
     * @param string $type Connection type or supported alias.
     * @param array<string, mixed> $parameters Connection parameters.
     */
    public static function connect(string $type, array $parameters = []): DatabaseAdapterInterface
    {
        $normalizedType = strtolower(trim($type));
        $parameters = self::normalizeParameters($parameters);

        return match ($normalizedType) {
            'mysql' => new MySQL(
                $parameters['host'] ?? false,
                $parameters['port'] ?? false,
                $parameters['name'] ?? false,
                $parameters['user'] ?? false,
                $parameters['password'] ?? false,
                self::adapterOptions($parameters),
            ),
            'postgresql', 'postgres', 'pgsql' => new PostgreSQL(
                $parameters['host'] ?? false,
                $parameters['port'] ?? false,
                $parameters['name'] ?? false,
                $parameters['user'] ?? false,
                $parameters['password'] ?? false,
                self::adapterOptions($parameters),
            ),
            'oracle', 'oci' => new Oracle(
                $parameters['host'] ?? null,
                $parameters['name'] ?? null,
                $parameters['user'] ?? null,
                $parameters['password'] ?? null,
                $parameters['use_host'] ?? null,
                $parameters['port'] ?? null,
                $parameters['storage'] ?? null,
                $parameters['charset'] ?? '',
                $parameters['no_connect'] ?? false,
                self::adapterOptions($parameters),
            ),
            'pdo_mysql', 'pdo_pgsql', 'pdo_oci', 'pdo_odbc', 'pdo_sqlite',
            'pdo_sqlsrv', 'pdo_dblib', 'pdo_firebird',
            'mysql_pdo', 'pgsql_pdo', 'oci_pdo', 'odbc_pdo', 'sqlite_pdo',
            'sqlsrv_pdo', 'dblib_pdo', 'firebird_pdo' => new PDOLIB(
                self::pdoType($normalizedType),
                $parameters['name'] ?? false,
                $parameters['user'] ?? false,
                $parameters['password'] ?? false,
                $parameters['host'] ?? false,
                $parameters['port'] ?? false,
                $parameters['oracle_connect_type'] ?? false,
                self::adapterOptions($parameters),
            ),
            default => throw new InvalidArgumentException(
                "Unsupported database type '$type'."
            ),
        };
    }

    /** Return the concrete adapter selected during construction. */
    public function getAdapter(): DatabaseAdapterInterface
    {
        return $this->adapter;
    }

    /** Forward adapter calls for facade-style usage. */
    public function __call(string $method, array $arguments): mixed
    {
        if (!method_exists($this->adapter, $method)) {
            throw new BadMethodCallException("Database adapter method '$method' does not exist.");
        }
        return $this->adapter->{$method}(...$arguments);
    }

    /** Normalize parameter aliases to the names used by adapter constructors. */
    private static function normalizeParameters(array $parameters): array
    {
        if (array_key_exists('database', $parameters) && !array_key_exists('name', $parameters)) {
            $parameters['name'] = $parameters['database'];
        }
        if (array_key_exists('username', $parameters) && !array_key_exists('user', $parameters)) {
            $parameters['user'] = $parameters['username'];
        }
        if (array_key_exists('pass', $parameters) && !array_key_exists('password', $parameters)) {
            $parameters['password'] = $parameters['pass'];
        }
        if (array_key_exists('p_connect', $parameters) && !array_key_exists('storage', $parameters)) {
            $parameters['storage'] = $parameters['p_connect'];
        }
        if (array_key_exists('use_host', $parameters) && !array_key_exists('oracle_connect_type', $parameters)) {
            $parameters['oracle_connect_type'] = $parameters['use_host'];
        }
        return $parameters;
    }

    /** Convert PDO aliases such as pdo_pgsql or mysql_pdo to PDOLIB's driver name. */
    private static function pdoType(string $type): string
    {
        $aliases = [
            'pdo_mysql' => 'mysql',
            'mysql_pdo' => 'mysql',
            'pdo_pgsql' => 'pgsql',
            'pgsql_pdo' => 'pgsql',
            'pdo_oci' => 'oci',
            'oci_pdo' => 'oci',
            'pdo_odbc' => 'odbc',
            'odbc_pdo' => 'odbc',
            'pdo_sqlite' => 'sqlite',
            'sqlite_pdo' => 'sqlite',
            'pdo_sqlsrv' => 'sqlsrv',
            'sqlsrv_pdo' => 'sqlsrv',
            'pdo_dblib' => 'dblib',
            'dblib_pdo' => 'dblib',
            'pdo_firebird' => 'firebird',
            'firebird_pdo' => 'firebird',
        ];

        return $aliases[$type] ?? $type;
    }

    /** Keep adapter-specific runtime options out of connection credentials. */
    private static function adapterOptions(array $parameters): array
    {
        $supported = ['storage', 'use_transaction', 'debug', 'error_exit', 'log_name', 'log_all'];
        return array_intersect_key($parameters, array_flip($supported));
    }
}
