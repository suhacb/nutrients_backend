<?php

namespace App\Console\Concerns;

trait DumpsDatabase
{
    protected function dumpDatabase(string $path): bool
    {
        try {
            $conn   = config('database.default');
            $config = config("database.connections.{$conn}");

            $host   = $config['host']     ?? '127.0.0.1';
            $port   = $config['port']     ?? 3306;
            $dbname = $config['database'] ?? '';
            $user   = $config['username'] ?? '';
            $pass   = $config['password'] ?? '';

            $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";
            $pdo = new \PDO($dsn, $user, $pass);
            $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

            $tables = $pdo->query('SHOW TABLES')->fetchAll(\PDO::FETCH_COLUMN);

            $fh = fopen($path, 'w');
            if ($fh === false) {
                throw new \RuntimeException("Could not open backup file for writing: {$path}");
            }

            fwrite($fh, "-- Backup: {$dbname} | " . now()->toIso8601String() . "\n\nSET FOREIGN_KEY_CHECKS=0;\n\n");

            foreach ($tables as $table) {
                $row = $pdo->query("SHOW CREATE TABLE `{$table}`")->fetch(\PDO::FETCH_NUM);
                fwrite($fh, "DROP TABLE IF EXISTS `{$table}`;\n{$row[1]};\n\n");

                $stmt = $pdo->query("SELECT * FROM `{$table}`");
                while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                    $cols   = implode(', ', array_map(fn($c) => "`{$c}`", array_keys($row)));
                    $values = implode(', ', array_map(fn($v) => $v === null ? 'NULL' : $pdo->quote((string) $v), $row));
                    fwrite($fh, "INSERT INTO `{$table}` ({$cols}) VALUES ({$values});\n");
                }

                fwrite($fh, "\n");
            }

            fwrite($fh, "SET FOREIGN_KEY_CHECKS=1;\n");
            fclose($fh);
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }
}
