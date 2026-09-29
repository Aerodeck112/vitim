<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Rulează migrările din app/Migrations (la instalare și după fiecare actualizare).
 */
final class Migrator
{
    public static function run(): array
    {
        DB::createTable('migrations', [
            'id' => 'id',
            'name' => 'string',
            'ran_at' => 'datetime?',
        ], [], [['name']]);

        $done = array_column(DB::all('SELECT name FROM migrations'), 'name');
        $files = glob(APP_PATH . '/Migrations/*.php') ?: [];
        sort($files, SORT_STRING);
        $ran = [];
        foreach ($files as $file) {
            $name = basename($file, '.php');
            if (in_array($name, $done, true)) {
                continue;
            }
            $fn = require $file;
            if (is_callable($fn)) {
                $fn();
            }
            DB::insert('migrations', ['name' => $name, 'ran_at' => DB::now()]);
            $ran[] = $name;
        }
        Settings::reset();
        return $ran;
    }

    public static function pending(): array
    {
        if (!DB::tableExists('migrations')) {
            return ['*'];
        }
        $done = array_column(DB::all('SELECT name FROM migrations'), 'name');
        $out = [];
        foreach (glob(APP_PATH . '/Migrations/*.php') ?: [] as $f) {
            $n = basename($f, '.php');
            if (!in_array($n, $done, true)) {
                $out[] = $n;
            }
        }
        return $out;
    }
}
