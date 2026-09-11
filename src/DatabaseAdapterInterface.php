<?php

declare(strict_types=1);

namespace Toropyga\DB;

interface DatabaseAdapterInterface
{
    public function getResults(string $sql, int|string $one = 0): mixed;

    public function query(string $sql): mixed;

    public function getTableList(): array|false;

    public function getInsertSQL(string $table, array $values): string|false;

    public function getUpdateSQL(string $table, array $values, array|false $index = false): string|false;

    public function getDeleteSQL(string $table, array|false $index = false): string|false;
}
