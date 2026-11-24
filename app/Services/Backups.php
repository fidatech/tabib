<?php

namespace App\Services;

use App\Models\Central\Backup;
use App\Models\Central\Tenant;
use App\Tenancy\Tenancy;
use Illuminate\Support\Facades\DB;
use PDO;
use RuntimeException;

/**
 * Copies of the database of a clinic, kept under storage/app/backups/<short name>/ as a
 * compressed file. Written by PHP itself, so nothing has to be installed on the server.
 * A MySQL copy is a list of SQL statements; an SQLite one is the database file.
 */
class Backups
{
    private const MARK = "\n-- §§ --\n";   // between two statements

    public static function make(Tenant $tenant, string $kind = 'auto', ?int $adminId = null): Backup
    {
        $dir = storage_path('app/backups/'.$tenant->slug);
        if (! is_dir($dir) && ! mkdir($dir, 0775, true) && ! is_dir($dir)) {
            throw new RuntimeException('The backup folder cannot be made: '.$dir);
        }
        $name = $tenant->slug.'/'.$tenant->slug.'-'.now()->format('Ymd-His').'-'.substr(bin2hex(random_bytes(3)), 0, 6);

        $file = app(Tenancy::class)->run($tenant, function () use ($name) {
            $connection = DB::connection();
            if ($connection->getDriverName() === 'sqlite') {
                $file = $name.'.sqlite.gz';
                self::gzipFile($connection->getDatabaseName(), storage_path('app/backups/'.$file));

                return $file;
            }
            $file = $name.'.sql.gz';
            self::dump($connection->getPdo(), storage_path('app/backups/'.$file));

            return $file;
        });

        return Backup::create(['tenant_id' => $tenant->id, 'file' => $file, 'size' => (int) @filesize(storage_path('app/backups/'.$file)), 'kind' => $kind, 'admin_id' => $adminId]);
    }

    /**
     * Put a copy back in place of what the clinic has now. What it has now is saved first, so
     * even this can be undone.
     */
    public static function restore(Backup $backup, ?int $adminId = null): void
    {
        $tenant = $backup->tenant;
        if (! is_file($backup->path())) {
            throw new RuntimeException('The backup file is missing.');
        }
        self::make($tenant, 'manual', $adminId);

        app(Tenancy::class)->run($tenant, function () use ($backup) {
            $connection = DB::connection();
            if (str_ends_with($backup->file, '.sqlite.gz')) {
                $target = $connection->getDatabaseName();
                $connection->disconnect();
                self::gunzipFile($backup->path(), $target);

                return;
            }
            $pdo = $connection->getPdo();
            $sql = '';
            $in = gzopen($backup->path(), 'rb');
            while (! gzeof($in)) {
                $sql .= gzread($in, 1 << 20);
                // run every whole statement read so far, keep the unfinished rest
                while (($at = strpos($sql, self::MARK)) !== false) {
                    $statement = trim(substr($sql, 0, $at));
                    $sql = substr($sql, $at + strlen(self::MARK));
                    if ($statement !== '') {
                        $pdo->exec($statement);
                    }
                }
            }
            gzclose($in);
            if (trim($sql) !== '') {
                $pdo->exec(trim($sql));
            }
        });
    }

    /** Remove the automatic copies older than the days the administrator keeps them. */
    public static function prune(int $keepDays): int
    {
        $removed = 0;
        foreach (Backup::where('kind', 'auto')->where('created_at', '<', now()->subDays(max(1, $keepDays)))->get() as $backup) {
            @unlink($backup->path());
            $backup->delete();
            $removed++;
        }

        return $removed;
    }

    public static function remove(Backup $backup): void
    {
        @unlink($backup->path());
        $backup->delete();
    }

    private static function dump(PDO $pdo, string $path): void
    {
        $out = gzopen($path, 'wb6');
        $write = fn (string $statement) => gzwrite($out, $statement.self::MARK);
        $write('SET FOREIGN_KEY_CHECKS=0');
        $write("SET NAMES utf8mb4");

        $tables = $pdo->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'")->fetchAll(PDO::FETCH_NUM);
        foreach ($tables as [$table]) {
            $quoted = '`'.str_replace('`', '``', $table).'`';
            $create = $pdo->query('SHOW CREATE TABLE '.$quoted)->fetch(PDO::FETCH_NUM)[1];
            $write('DROP TABLE IF EXISTS '.$quoted);
            $write($create);

            $rows = $pdo->query('SELECT * FROM '.$quoted, PDO::FETCH_ASSOC);
            $batch = [];
            $columns = null;
            $flush = function () use (&$batch, &$columns, $quoted, $write) {
                if ($batch) {
                    $write('INSERT INTO '.$quoted.' ('.$columns.') VALUES '.implode(',', $batch));
                    $batch = [];
                }
            };
            foreach ($rows as $row) {
                $columns ??= implode(',', array_map(fn (string $c) => '`'.str_replace('`', '``', $c).'`', array_keys($row)));
                $batch[] = '('.implode(',', array_map(fn ($v) => $v === null ? 'NULL' : $pdo->quote((string) $v), $row)).')';
                if (count($batch) >= 200) {
                    $flush();
                }
            }
            $flush();
        }
        $write('SET FOREIGN_KEY_CHECKS=1');
        gzclose($out);
    }

    private static function gzipFile(string $from, string $to): void
    {
        $in = fopen($from, 'rb');
        $out = gzopen($to, 'wb6');
        while (! feof($in)) {
            gzwrite($out, (string) fread($in, 1 << 20));
        }
        fclose($in);
        gzclose($out);
    }

    private static function gunzipFile(string $from, string $to): void
    {
        $in = gzopen($from, 'rb');
        $out = fopen($to, 'wb');
        while (! gzeof($in)) {
            fwrite($out, (string) gzread($in, 1 << 20));
        }
        gzclose($in);
        fclose($out);
    }
}
