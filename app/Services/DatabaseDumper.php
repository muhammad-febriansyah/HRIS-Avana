<?php

namespace App\Services;

use Generator;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * A SQL dump of the application's own database, produced in PHP.
 *
 * `mysqldump` is not reachable from every deployment — a shared VPS often
 * disables `exec`, and the binary is frequently absent from a PHP-only image —
 * so the dump is assembled from ordinary queries instead. That also keeps the
 * export working on the SQLite the test suite runs against.
 *
 * Output is yielded a chunk at a time rather than built into a string: a
 * production database will not fit in a request's memory, and the caller
 * streams what it is given straight to the client.
 */
final class DatabaseDumper
{
    /**
     * Rows read (and written) per batch.
     */
    private const CHUNK = 500;

    private Connection $connection;

    private string $driver;

    public function __construct(?string $connectionName = null)
    {
        $this->connection = DB::connection($connectionName);
        $this->driver = $this->connection->getDriverName();

        if (! in_array($this->driver, ['mysql', 'mariadb', 'sqlite'], true)) {
            throw new RuntimeException("Dump belum mendukung driver '{$this->driver}'.");
        }
    }

    /**
     * Every base table in the database, in name order.
     *
     * @return array<int, string>
     */
    public function tables(): array
    {
        if ($this->driver === 'sqlite') {
            return $this->connection->table('sqlite_master')
                ->where('type', 'table')
                ->where('name', 'not like', 'sqlite_%')
                ->orderBy('name')
                ->pluck('name')
                ->all();
        }

        return array_map(
            fn (object $row): string => array_values((array) $row)[0],
            $this->connection->select('SHOW FULL TABLES WHERE Table_type = "BASE TABLE"'),
        );
    }

    /**
     * Row counts per table, for the screen that offers the download.
     *
     * @param  array<int, string>  $tables
     * @return array<int, array{name: string, rows: int}>
     */
    public function summary(array $tables): array
    {
        return array_values(array_map(fn (string $table): array => [
            'name' => $table,
            'rows' => (int) $this->connection->table($table)->count(),
        ], $tables));
    }

    /**
     * The dump itself, one string at a time.
     *
     * @param  array<int, string>  $tables
     * @return Generator<int, string>
     */
    public function dump(array $tables, bool $withData = true): Generator
    {
        yield $this->header();

        foreach ($tables as $table) {
            yield $this->structureFor($table);

            if ($withData) {
                yield from $this->dataFor($table);
            }
        }

        yield $this->footer();
    }

    /**
     * The preamble: a note about where the file came from, and the session
     * settings a restore needs to accept rows in table order.
     */
    private function header(): string
    {
        $name = $this->connection->getDatabaseName();
        $stamp = now()->toDateTimeString();

        $lines = [
            '-- AvanaHR database export',
            "-- Database: {$name}",
            "-- Dibuat  : {$stamp}",
            '',
        ];

        if ($this->driver !== 'sqlite') {
            $lines[] = 'SET NAMES utf8mb4;';
            $lines[] = 'SET FOREIGN_KEY_CHECKS = 0;';
            $lines[] = 'SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";';
            $lines[] = '';
        }

        return implode("\n", $lines)."\n";
    }

    /**
     * Restore the checks the header switched off.
     */
    private function footer(): string
    {
        return $this->driver === 'sqlite'
            ? "\n-- selesai\n"
            : "\nSET FOREIGN_KEY_CHECKS = 1;\n-- selesai\n";
    }

    /**
     * `DROP` + `CREATE` for one table.
     */
    private function structureFor(string $table): string
    {
        $quoted = $this->quoteIdentifier($table);

        if ($this->driver === 'sqlite') {
            $sql = (string) $this->connection->table('sqlite_master')
                ->where('type', 'table')
                ->where('name', $table)
                ->value('sql');

            return "\n-- Struktur {$table}\nDROP TABLE IF EXISTS {$quoted};\n{$sql};\n";
        }

        $row = (array) $this->connection->selectOne("SHOW CREATE TABLE {$quoted}");
        $create = $row['Create Table'] ?? '';

        return "\n-- Struktur {$table}\nDROP TABLE IF EXISTS {$quoted};\n{$create};\n";
    }

    /**
     * `INSERT` statements for one table, a chunk at a time.
     *
     * Ordered by primary key where there is one, so two dumps of an unchanged
     * table are identical and a diff between them means something.
     *
     * @return Generator<int, string>
     */
    private function dataFor(string $table): Generator
    {
        $quoted = $this->quoteIdentifier($table);
        $query = $this->connection->table($table);
        $key = $this->primaryKeyFor($table);

        if ($key !== null) {
            $query->orderBy($key);
        }

        $first = true;

        foreach ($query->lazy(self::CHUNK) as $row) {
            $values = (array) $row;

            if ($first) {
                yield "\n-- Data {$table}\n";
                $first = false;
            }

            $columns = implode(', ', array_map(
                fn (string $column): string => $this->quoteIdentifier($column),
                array_keys($values),
            ));

            $literals = implode(', ', array_map(
                fn (mixed $value): string => $this->literal($value),
                array_values($values),
            ));

            yield "INSERT INTO {$quoted} ({$columns}) VALUES ({$literals});\n";
        }
    }

    /**
     * The table's single-column primary key, or null when it has none.
     */
    private function primaryKeyFor(string $table): ?string
    {
        if ($this->driver === 'sqlite') {
            foreach ($this->connection->select("PRAGMA table_info({$this->quoteIdentifier($table)})") as $column) {
                if ((int) ($column->pk ?? 0) === 1) {
                    return (string) $column->name;
                }
            }

            return null;
        }

        $keys = $this->connection->select("SHOW KEYS FROM {$this->quoteIdentifier($table)} WHERE Key_name = 'PRIMARY'");

        return count($keys) === 1 ? (string) $keys[0]->Column_name : null;
    }

    /**
     * One value as a SQL literal, escaped by the driver rather than by hand.
     */
    private function literal(mixed $value): string
    {
        if ($value === null) {
            return 'NULL';
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        return (string) $this->connection->getPdo()->quote((string) $value);
    }

    /**
     * Wrap an identifier the way this driver expects.
     */
    private function quoteIdentifier(string $name): string
    {
        $escaped = str_replace('`', '``', $name);

        return $this->driver === 'sqlite' ? '"'.str_replace('"', '""', $name).'"' : "`{$escaped}`";
    }
}
