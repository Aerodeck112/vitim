<?php
declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOStatement;

/**
 * Strat subțire peste PDO, compatibil MySQL/MariaDB (cPanel) și SQLite.
 */
final class DB
{
    private static ?PDO $pdo = null;
    private static string $driver = 'sqlite';

    public static function connect(array $cfg): void
    {
        self::$driver = $cfg['driver'] ?? 'sqlite';
        $opts = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        if (self::$driver === 'mysql') {
            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
                $cfg['host'] ?? 'localhost',
                (int)($cfg['port'] ?? 3306),
                $cfg['name'] ?? ''
            );
            self::$pdo = new PDO($dsn, $cfg['user'] ?? '', $cfg['pass'] ?? '', $opts);
            self::$pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci, time_zone = '+00:00'");
        } else {
            $path = $cfg['path'] ?? (STORAGE_PATH . '/database.sqlite');
            self::$pdo = new PDO('sqlite:' . $path, null, null, $opts);
            self::$pdo->exec('PRAGMA journal_mode = WAL; PRAGMA foreign_keys = ON; PRAGMA busy_timeout = 5000;');
        }
    }

    public static function pdo(): PDO
    {
        if (!self::$pdo) {
            throw new \RuntimeException('Baza de date nu este conectată.');
        }
        return self::$pdo;
    }

    public static function connected(): bool
    {
        return self::$pdo !== null;
    }

    public static function driver(): string
    {
        return self::$driver;
    }

    public static function q(string $sql, array $params = []): PDOStatement
    {
        $st = self::pdo()->prepare($sql);
        foreach ($params as $k => $v) {
            $key = is_int($k) ? $k + 1 : (str_starts_with((string)$k, ':') ? $k : ':' . $k);
            $type = is_int($v) ? PDO::PARAM_INT : (is_bool($v) ? PDO::PARAM_BOOL : (is_null($v) ? PDO::PARAM_NULL : PDO::PARAM_STR));
            $st->bindValue($key, is_bool($v) ? (int)$v : $v, $type);
        }
        $st->execute();
        return $st;
    }

    public static function all(string $sql, array $params = []): array
    {
        return self::q($sql, $params)->fetchAll();
    }

    public static function row(string $sql, array $params = []): ?array
    {
        $r = self::q($sql, $params)->fetch();
        return $r === false ? null : $r;
    }

    public static function val(string $sql, array $params = []): mixed
    {
        $r = self::q($sql, $params)->fetchColumn();
        return $r === false ? null : $r;
    }

    public static function insert(string $table, array $data): int
    {
        $cols = array_keys($data);
        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $table,
            implode(', ', $cols),
            implode(', ', array_map(fn($c) => ':' . $c, $cols))
        );
        self::q($sql, $data);
        return (int)self::pdo()->lastInsertId();
    }

    public static function update(string $table, array $data, string $where, array $params = []): int
    {
        $sets = [];
        $bind = [];
        foreach ($data as $k => $v) {
            $sets[] = "$k = :set_$k";
            $bind["set_$k"] = $v;
        }
        $sql = sprintf('UPDATE %s SET %s WHERE %s', $table, implode(', ', $sets), $where);
        return self::q($sql, $bind + $params)->rowCount();
    }

    public static function delete(string $table, string $where, array $params = []): int
    {
        return self::q("DELETE FROM $table WHERE $where", $params)->rowCount();
    }

    public static function now(): string
    {
        return gmdate('Y-m-d H:i:s');
    }

    public static function tableExists(string $table): bool
    {
        try {
            if (self::$driver === 'mysql') {
                return (bool)self::val('SHOW TABLES LIKE ' . self::pdo()->quote($table));
            }
            return (bool)self::val("SELECT name FROM sqlite_master WHERE type='table' AND name = ?", [$table]);
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Creează tabel portabil. Tipuri: id, string, text, int, bool, datetime, decimal, json.
     * Sufix "?" = nullable, "=val" = default.
     */
    public static function createTable(string $table, array $columns, array $indexes = [], array $unique = []): void
    {
        $mysql = self::$driver === 'mysql';
        $defs = [];
        foreach ($columns as $name => $spec) {
            $nullable = str_contains($spec, '?');
            $default = null;
            if (str_contains($spec, '=')) {
                [$spec, $default] = explode('=', $spec, 2);
            }
            $type = rtrim($spec, '?');
            $sqlType = match ($type) {
                'id' => $mysql ? 'INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT',
                'string' => $mysql ? 'VARCHAR(255)' : 'TEXT',
                'short' => $mysql ? 'VARCHAR(64)' : 'TEXT',
                'text', 'json' => $mysql ? 'MEDIUMTEXT' : 'TEXT',
                'int' => $mysql ? 'INT' : 'INTEGER',
                'bool' => $mysql ? 'TINYINT(1)' : 'INTEGER',
                'datetime' => $mysql ? 'DATETIME' : 'TEXT',
                'decimal' => $mysql ? 'DECIMAL(12,2)' : 'REAL',
                default => throw new \InvalidArgumentException("Tip necunoscut: $type"),
            };
            if (in_array($type, ['text', 'json'], true)) {
                // MySQL < 8.0.13 nu acceptă DEFAULT pe TEXT; ținem coloanele TEXT nullable peste tot
                $sqlType .= ' NULL';
            } elseif ($type !== 'id') {
                $sqlType .= $nullable ? ' NULL' : ' NOT NULL';
                if ($default !== null) {
                    $sqlType .= ' DEFAULT ' . (is_numeric($default) ? $default : self::pdo()->quote($default));
                } elseif (!$nullable && in_array($type, ['string', 'short'], true)) {
                    $sqlType .= " DEFAULT ''";
                } elseif (!$nullable && in_array($type, ['int', 'bool'], true)) {
                    $sqlType .= ' DEFAULT 0';
                }
            }
            $defs[] = "$name $sqlType";
        }
        $sql = "CREATE TABLE IF NOT EXISTS $table (\n  " . implode(",\n  ", $defs) . "\n)";
        if ($mysql) {
            $sql .= ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
        }
        self::pdo()->exec($sql);
        foreach ($indexes as $cols) {
            self::createIndex($table, (array)$cols, false);
        }
        foreach ($unique as $cols) {
            self::createIndex($table, (array)$cols, true);
        }
    }

    public static function createIndex(string $table, array $cols, bool $unique = false): void
    {
        $name = ($unique ? 'uq_' : 'ix_') . $table . '_' . implode('_', $cols);
        $u = $unique ? 'UNIQUE ' : '';
        if (self::$driver === 'mysql') {
            $exists = self::val("SHOW INDEX FROM $table WHERE Key_name = ?", [$name]);
            if ($exists) {
                return;
            }
            // coloanele VARCHAR(255) utf8mb4 încap în limita de 3072 bytes a InnoDB
            self::pdo()->exec("CREATE {$u}INDEX $name ON $table (" . implode(', ', $cols) . ')');
        } else {
            self::pdo()->exec("CREATE {$u}INDEX IF NOT EXISTS $name ON $table (" . implode(', ', $cols) . ')');
        }
    }

    public static function addColumn(string $table, string $name, string $spec): void
    {
        $cols = self::columns($table);
        if (in_array($name, $cols, true)) {
            return;
        }
        $mysql = self::$driver === 'mysql';
        $nullable = str_contains($spec, '?');
        $type = rtrim($spec, '?');
        $sqlType = match ($type) {
            'string' => $mysql ? 'VARCHAR(255)' : 'TEXT',
            'text', 'json' => $mysql ? 'MEDIUMTEXT' : 'TEXT',
            'int', 'bool' => $mysql ? 'INT' : 'INTEGER',
            'datetime' => $mysql ? 'DATETIME' : 'TEXT',
            'decimal' => $mysql ? 'DECIMAL(12,2)' : 'REAL',
            default => 'TEXT',
        };
        $sqlType .= $nullable ? ' NULL' : (in_array($type, ['int', 'bool', 'decimal'], true) ? ' NOT NULL DEFAULT 0' : " NOT NULL DEFAULT ''");
        if ($mysql && in_array($type, ['text', 'json'], true) && !$nullable) {
            $sqlType = 'MEDIUMTEXT NULL';
        }
        self::pdo()->exec("ALTER TABLE $table ADD COLUMN $name $sqlType");
    }

    public static function columns(string $table): array
    {
        if (self::$driver === 'mysql') {
            return array_column(self::all("SHOW COLUMNS FROM $table"), 'Field');
        }
        return array_column(self::all("PRAGMA table_info($table)"), 'name');
    }

    public static function transaction(callable $fn): mixed
    {
        $pdo = self::pdo();
        $pdo->beginTransaction();
        try {
            $r = $fn();
            $pdo->commit();
            return $r;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
}
