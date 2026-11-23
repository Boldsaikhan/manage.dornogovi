<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Throwable;

/**
 * Өгөгдлийн сангийн үзүүлэлт болон SQL нөөц.
 *
 * Том DB-д санах ой / timeout-оос зайлсхийхийн тулд dump-ыг файл руу
 * урсгалтай бичиж, mysqldump боломжтой бол түүнийг илүүд үзнэ.
 */
class DatabaseBackup
{
    /** Хуучин түр файлыг ийм хугацааны дараа устгана. */
    private const STALE_SECONDS = 3600;

    public static function directory(): string
    {
        return storage_path('app/backups');
    }

    /**
     * @return array{
     *     name: string,
     *     driver: string,
     *     host: string|null,
     *     table_count: int,
     *     total_rows: int|null,
     *     total_bytes: int|null,
     *     total_size: string,
     *     engine: string|null,
     *     tables: list<array{
     *         name: string,
     *         engine: string|null,
     *         rows: int|null,
     *         data_bytes: int|null,
     *         index_bytes: int|null,
     *         total_bytes: int|null,
     *         size: string
     *     }>
     * }
     */
    public static function stats(): array
    {
        $connection = (string) config('database.default');
        $config = (array) config("database.connections.{$connection}", []);
        $driver = (string) ($config['driver'] ?? $connection);
        $name = self::databaseName($config, $driver);

        $tables = match ($driver) {
            'mysql', 'mariadb' => self::mysqlTableStats($name),
            'sqlite' => self::sqliteTableStats($config),
            default => self::genericTableStats(),
        };

        $totalRows = 0;
        $rowsKnown = false;
        $totalBytes = 0;
        $bytesKnown = false;
        $engines = [];

        foreach ($tables as $table) {
            if ($table['rows'] !== null) {
                $totalRows += $table['rows'];
                $rowsKnown = true;
            }
            if ($table['total_bytes'] !== null) {
                $totalBytes += $table['total_bytes'];
                $bytesKnown = true;
            }
            if ($table['engine']) {
                $engines[$table['engine']] = true;
            }
        }

        return [
            'name' => $name,
            'driver' => $driver,
            'host' => isset($config['host']) ? (string) $config['host'] : null,
            'table_count' => count($tables),
            'total_rows' => $rowsKnown ? $totalRows : null,
            'total_bytes' => $bytesKnown ? $totalBytes : null,
            'total_size' => $bytesKnown ? self::formatBytes($totalBytes) : '—',
            'engine' => count($engines) === 1 ? array_key_first($engines) : (count($engines) > 1 ? 'mixed' : null),
            'tables' => $tables,
        ];
    }

    /**
     * SQL dump файл үүсгээд замыг буцаана. Дуудсан тал нь download
     * дууссаны дараа устгах үүрэгтэй.
     */
    public static function createDump(): string
    {
        @ini_set('memory_limit', '512M');
        @set_time_limit(0);

        $dir = self::directory();
        if (! is_dir($dir) && ! mkdir($dir, 0755, true) && ! is_dir($dir)) {
            throw new RuntimeException('Нөөц хавтас үүсгэж чадсангүй.');
        }

        self::purgeStale();

        $connection = (string) config('database.default');
        $config = (array) config("database.connections.{$connection}", []);
        $driver = (string) ($config['driver'] ?? $connection);

        $stamp = now()->format('Y-m-d-His');
        $safeName = preg_replace('/[^a-zA-Z0-9._-]+/', '-', self::databaseName($config, $driver) ?: 'database') ?: 'database';
        $path = $dir.DIRECTORY_SEPARATOR."{$safeName}-{$stamp}.sql";

        if (in_array($driver, ['mysql', 'mariadb'], true) && self::tryMysqldump($path, $config)) {
            return $path;
        }

        self::phpDump($path, $driver, $config);

        return $path;
    }

    public static function purgeStale(): void
    {
        $dir = self::directory();
        if (! is_dir($dir)) {
            return;
        }

        $cutoff = time() - self::STALE_SECONDS;

        foreach (glob($dir.DIRECTORY_SEPARATOR.'*.sql') ?: [] as $file) {
            if (is_file($file) && filemtime($file) < $cutoff) {
                @unlink($file);
            }
        }
    }

    public static function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes.' B';
        }

        $units = ['KB', 'MB', 'GB', 'TB'];
        $value = (float) $bytes;
        foreach ($units as $unit) {
            $value /= 1024;
            if ($value < 1024) {
                return round($value, $value >= 10 ? 1 : 2).' '.$unit;
            }
        }

        return round($value, 1).' PB';
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private static function databaseName(array $config, string $driver): string
    {
        if ($driver === 'sqlite') {
            $database = (string) ($config['database'] ?? '');

            return $database === ':memory:' ? ':memory:' : basename($database);
        }

        return (string) ($config['database'] ?? '');
    }

    /**
     * @return list<array{name: string, engine: string|null, rows: int|null, data_bytes: int|null, index_bytes: int|null, total_bytes: int|null, size: string}>
     */
    private static function mysqlTableStats(string $schema): array
    {
        if ($schema === '') {
            return [];
        }

        $rows = DB::select(
            'SELECT TABLE_NAME AS name, ENGINE AS engine, TABLE_ROWS AS table_rows,
                    DATA_LENGTH AS data_length, INDEX_LENGTH AS index_length
             FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = ?
             ORDER BY TABLE_NAME',
            [$schema]
        );

        return array_map(function ($row) {
            $data = (int) ($row->data_length ?? 0);
            $index = (int) ($row->index_length ?? 0);
            $total = $data + $index;

            return [
                'name' => (string) $row->name,
                'engine' => $row->engine !== null ? (string) $row->engine : null,
                'rows' => $row->table_rows !== null ? (int) $row->table_rows : null,
                'data_bytes' => $data,
                'index_bytes' => $index,
                'total_bytes' => $total,
                'size' => self::formatBytes($total),
            ];
        }, $rows);
    }

    /**
     * @param  array<string, mixed>  $config
     * @return list<array{name: string, engine: string|null, rows: int|null, data_bytes: int|null, index_bytes: int|null, total_bytes: int|null, size: string}>
     */
    private static function sqliteTableStats(array $config): array
    {
        $names = collect(DB::select("SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%' ORDER BY name"))
            ->pluck('name')
            ->map(fn ($n) => (string) $n)
            ->all();

        $dbBytes = null;
        $database = (string) ($config['database'] ?? '');
        if ($database !== '' && $database !== ':memory:' && is_file($database)) {
            $dbBytes = (int) filesize($database);
        } else {
            try {
                $pageCount = (int) (DB::selectOne('PRAGMA page_count')->page_count ?? 0);
                $pageSize = (int) (DB::selectOne('PRAGMA page_size')->page_size ?? 0);
                if ($pageCount > 0 && $pageSize > 0) {
                    $dbBytes = $pageCount * $pageSize;
                }
            } catch (Throwable) {
                $dbBytes = null;
            }
        }

        $perTable = count($names) > 0 && $dbBytes !== null
            ? (int) floor($dbBytes / count($names))
            : null;

        return array_map(function (string $name) use ($perTable) {
            $rows = null;
            try {
                $rows = (int) DB::table($name)->count();
            } catch (Throwable) {
                $rows = null;
            }

            return [
                'name' => $name,
                'engine' => 'sqlite',
                'rows' => $rows,
                'data_bytes' => $perTable,
                'index_bytes' => null,
                'total_bytes' => $perTable,
                'size' => $perTable !== null ? self::formatBytes($perTable) : '—',
            ];
        }, $names);
    }

    /**
     * @return list<array{name: string, engine: string|null, rows: int|null, data_bytes: int|null, index_bytes: int|null, total_bytes: int|null, size: string}>
     */
    private static function genericTableStats(): array
    {
        return collect(self::tableNames())
            ->map(function (string $name) {
                $rows = null;
                try {
                    $rows = (int) DB::table($name)->count();
                } catch (Throwable) {
                    $rows = null;
                }

                return [
                    'name' => $name,
                    'engine' => null,
                    'rows' => $rows,
                    'data_bytes' => null,
                    'index_bytes' => null,
                    'total_bytes' => null,
                    'size' => '—',
                ];
            })
            ->all();
    }

    /**
     * Laravel SQLite дээр «main.users» гэж ирдэг тул schema угтварыг хасна.
     *
     * @return list<string>
     */
    private static function tableNames(): array
    {
        return collect(Schema::getTableListing())
            ->map(fn ($name) => self::normalizeTableName((string) $name))
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    private static function normalizeTableName(string $name): string
    {
        $name = trim($name);
        if ($name === '') {
            return '';
        }

        if (str_contains($name, '.')) {
            $name = substr($name, strrpos($name, '.') + 1);
        }

        return trim($name, " `\"'");
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private static function tryMysqldump(string $path, array $config): bool
    {
        $binary = self::findMysqldump();
        if ($binary === null) {
            return false;
        }

        $cnf = tempnam(sys_get_temp_dir(), 'mydump');
        if ($cnf === false) {
            return false;
        }

        $password = (string) ($config['password'] ?? '');
        $cnfBody = "[client]\n"
            ."user=".self::cnfEscape((string) ($config['username'] ?? ''))."\n"
            .'password="'.self::cnfEscape($password)."\"\n"
            ."host=".self::cnfEscape((string) ($config['host'] ?? '127.0.0.1'))."\n"
            ."port=".(int) ($config['port'] ?? 3306)."\n";

        file_put_contents($cnf, $cnfBody);

        $database = (string) ($config['database'] ?? '');
        $command = [
            $binary,
            '--defaults-extra-file='.$cnf,
            '--single-transaction',
            '--routines',
            '--triggers',
            '--hex-blob',
            '--default-character-set=utf8mb4',
            '--result-file='.$path,
            $database,
        ];

        $descriptor = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($command, $descriptor, $pipes, null, null, ['bypass_shell' => true]);

        if (! is_resource($process)) {
            @unlink($cnf);

            return false;
        }

        fclose($pipes[0]);
        stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $code = proc_close($process);
        @unlink($cnf);

        if ($code !== 0 || ! is_file($path) || filesize($path) === 0) {
            @unlink($path);
            report(new RuntimeException('mysqldump амжилтгүй: '.trim((string) $stderr)));

            return false;
        }

        return true;
    }

    private static function findMysqldump(): ?string
    {
        $candidates = [
            'mysqldump',
            'C:\\xampp\\mysql\\bin\\mysqldump.exe',
            '/usr/bin/mysqldump',
            '/usr/local/bin/mysqldump',
            '/usr/local/mysql/bin/mysqldump',
        ];

        foreach ($candidates as $candidate) {
            if ($candidate === 'mysqldump') {
                $which = PHP_OS_FAMILY === 'Windows' ? 'where mysqldump' : 'command -v mysqldump';
                $found = @shell_exec($which);
                $found = is_string($found) ? trim(explode("\n", $found)[0] ?? '') : '';
                if ($found !== '' && is_executable($found)) {
                    return $found;
                }

                continue;
            }

            if (is_file($candidate) && (PHP_OS_FAMILY === 'Windows' || is_executable($candidate))) {
                return $candidate;
            }
        }

        return null;
    }

    private static function cnfEscape(string $value): string
    {
        return str_replace(['\\', '"'], ['\\\\', '\\"'], $value);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private static function phpDump(string $path, string $driver, array $config): void
    {
        $handle = fopen($path, 'wb');
        if ($handle === false) {
            throw new RuntimeException('Нөөц файл нээж чадсангүй.');
        }

        try {
            $name = self::databaseName($config, $driver);
            fwrite($handle, "-- manage.dornogovi.gov.mn database backup\n");
            fwrite($handle, '-- Generated: '.now()->toDateTimeString()."\n");
            fwrite($handle, "-- Driver: {$driver}\n");
            fwrite($handle, "-- Database: {$name}\n\n");
            fwrite($handle, "SET NAMES utf8mb4;\n");
            fwrite($handle, "SET FOREIGN_KEY_CHECKS=0;\n\n");

            foreach (self::tableNames() as $table) {
                self::writeTableDump($handle, $table, $driver);
            }

            fwrite($handle, "SET FOREIGN_KEY_CHECKS=1;\n");
        } finally {
            fclose($handle);
        }
    }

    /** @param  resource  $handle */
    private static function writeTableDump($handle, string $table, string $driver): void
    {
        fwrite($handle, "-- ----------------------------\n");
        fwrite($handle, "-- Table: {$table}\n");
        fwrite($handle, "-- ----------------------------\n");
        fwrite($handle, "DROP TABLE IF EXISTS `{$table}`;\n");

        $createSql = self::createStatement($table, $driver);
        if ($createSql !== null) {
            fwrite($handle, $createSql.";\n\n");
        }

        foreach (DB::table($table)->cursor() as $row) {
            $values = [];
            foreach ((array) $row as $value) {
                $values[] = self::sqlLiteral($value);
            }
            fwrite($handle, 'INSERT INTO `'.$table.'` VALUES ('.implode(', ', $values).");\n");
        }

        fwrite($handle, "\n");
    }

    private static function createStatement(string $table, string $driver): ?string
    {
        try {
            if (in_array($driver, ['mysql', 'mariadb'], true)) {
                $row = DB::selectOne("SHOW CREATE TABLE `{$table}`");
                $sql = $row->{'Create Table'} ?? null;

                return is_string($sql) ? $sql : null;
            }

            if ($driver === 'sqlite') {
                $row = DB::selectOne(
                    'SELECT sql FROM sqlite_master WHERE type = ? AND name = ?',
                    ['table', $table]
                );

                return isset($row->sql) && is_string($row->sql) ? $row->sql : null;
            }
        } catch (Throwable) {
            return null;
        }

        return null;
    }

    private static function sqlLiteral(mixed $value): string
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

        if (is_resource($value)) {
            $value = stream_get_contents($value) ?: '';
        }

        $string = (string) $value;

        // Binary-ish content → hex, so restore stays valid.
        if (! mb_check_encoding($string, 'UTF-8') || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', $string)) {
            return "X'".bin2hex($string)."'";
        }

        return "'".str_replace(["\\", "\0", "\n", "\r", "'", '"', "\x1a"], ['\\\\', '\\0', '\\n', '\\r', "\\'", '\\"', '\\Z'], $string)."'";
    }
}
