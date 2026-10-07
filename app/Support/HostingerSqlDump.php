<?php

namespace App\Support;

use Illuminate\Database\Connection;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Throwable;

/**
 * Exporte la base courante en SQL importable sur Hostinger.
 * N'exécute que des lectures, puis annule la transaction.
 */
class HostingerSqlDump
{
    /**
     * @return array{database: string, tables: int, inserts: int, path: string, ok: bool, warnings: list<string>}
     */
    public function export(Connection $connection, string $path): array
    {
        if (! in_array($connection->getDriverName(), ['mysql', 'mariadb'], true)) {
            throw new RuntimeException('Cet export exige la connexion MySQL configurée dans .env.');
        }

        $pdo = $connection->getPdo();
        try {
            $pdo->exec('SET SESSION TRANSACTION READ ONLY');
        } catch (Throwable) {
            // La session reste en lecture : seules des requêtes SELECT et SHOW suivent.
        }

        $connection->beginTransaction();

        try {
            $database = (string) $connection->getDatabaseName();
            if ($database === '' || strcasecmp($database, 'twende_market') === 0) {
                throw new RuntimeException('La base configurée est refusée. Utilisez la base indiquée dans .env.');
            }
            $tables = $this->baseTables($connection);
            $ordered = $this->ordered($connection, $tables);
            $counts = [];
            $parts = [$this->header($database, count($ordered))];

            foreach (array_reverse($ordered) as $table) {
                $parts[] = 'DROP TABLE IF EXISTS '.$this->ident($table).';';
            }

            $parts[] = '';

            foreach ($ordered as $table) {
                $create = $connection->selectOne('SHOW CREATE TABLE '.$this->ident($table));
                $statement = $this->createStatement($create);
                $parts[] = self::normalizeCreate($statement).';';
                $parts[] = '';

                $rows = $connection->select('SELECT * FROM '.$this->ident($table));
                $counts[$table] = count($rows);
                if ($rows === []) {
                    $parts[] = '-- '.$this->ident($table).' : 0 ligne';
                    $parts[] = '';

                    continue;
                }

                $columns = array_keys((array) $rows[0]);
                $columnList = implode(', ', array_map($this->ident(...), $columns));
                foreach ($rows as $row) {
                    $values = [];
                    $data = (array) $row;
                    foreach ($columns as $column) {
                        $values[] = $this->literal($pdo, $data[$column] ?? null);
                    }
                    $parts[] = 'INSERT INTO '.$this->ident($table).' ('.$columnList.') VALUES ('.implode(', ', $values).');';
                }
                $parts[] = '';
            }

            $parts[] = 'SET FOREIGN_KEY_CHECKS=1;';
            $parts[] = 'SET UNIQUE_CHECKS=1;';
            $parts[] = '';
            $sql = implode("\n", $parts);
        } finally {
            $connection->rollBack();
        }

        $sql = $this->removeConfiguredSecrets($sql);
        $warnings = [];

        $check = self::verify($sql, count($ordered), $counts);
        if (! $check['ok']) {
            throw new RuntimeException('Export refusé : '.implode(' ', $check['errors']));
        }

        File::ensureDirectoryExists(dirname($path));
        File::put($path, $sql);

        return [
            'database' => $database,
            'tables' => count($ordered),
            'inserts' => array_sum($counts),
            'path' => $path,
            'ok' => true,
            'warnings' => $warnings,
        ];
    }

    public static function normalizeCreate(string $sql): string
    {
        $sql = preg_replace('/DEFINER=`[^`]*`@`[^`]*`\s*/i', '', $sql) ?? $sql;
        $sql = preg_replace('/\s*SQL SECURITY DEFINER/i', '', $sql) ?? $sql;
        $sql = str_ireplace(
            ['utf8mb4_0900_ai_ci', 'utf8mb4_0900_as_ci', 'utf8mb4_0900_as_cs', 'utf8mb4_0900_bin'],
            ['utf8mb4_unicode_ci', 'utf8mb4_unicode_ci', 'utf8mb4_unicode_ci', 'utf8mb4_bin'],
            $sql,
        );
        $sql = preg_replace('/\s+INVISIBLE\b/i', '', $sql) ?? $sql;
        $sql = str_replace('/*!999999\- enable the sandbox mode */', '', $sql);

        return rtrim($sql, " \t;");
    }

    /**
     * @param  array<string, int>  $rowCounts
     * @return array{ok: bool, errors: list<string>}
     */
    public static function verify(string $sql, int $tableCount, array $rowCounts): array
    {
        $errors = [];

        if (preg_match('/CREATE\s+DATABASE/i', $sql) === 1) {
            $errors[] = 'CREATE DATABASE est présent.';
        }
        if (preg_match('/^USE\s+/mi', $sql) === 1) {
            $errors[] = 'USE est présent.';
        }
        if (stripos($sql, 'DEFINER') !== false) {
            $errors[] = 'DEFINER est présent.';
        }
        if (stripos($sql, 'utf8mb4_0900') !== false) {
            $errors[] = 'Une collation MySQL 8 utf8mb4_0900 est présente.';
        }
        if (str_contains($sql, 'enable the sandbox mode')) {
            $errors[] = 'Le mode sandbox de mysqldump est présent.';
        }
        foreach (['IKPAY_SECRET_KEY', 'IKEEPAY_SECRET_KEY', 'APP_KEY'] as $secretName) {
            if (str_contains($sql, $secretName)) {
                $errors[] = $secretName.' est présent.';
            }
        }
        if (! str_contains($sql, 'SET FOREIGN_KEY_CHECKS=0;')) {
            $errors[] = 'FOREIGN_KEY_CHECKS=0 est absent.';
        }
        if (! str_contains($sql, 'SET NAMES utf8mb4;')) {
            $errors[] = 'SET NAMES utf8mb4 est absent.';
        }

        preg_match_all('/^CREATE TABLE\s+/mi', $sql, $creates);
        if (count($creates[0]) !== $tableCount) {
            $errors[] = 'Nombre de CREATE TABLE inattendu.';
        }

        foreach ($rowCounts as $table => $count) {
            $pattern = '/^INSERT INTO '.preg_quote(self::quoteIdent($table), '/').' /m';
            preg_match_all($pattern, $sql, $inserts);
            if (count($inserts[0]) !== $count) {
                $errors[] = 'Le nombre de lignes de '.$table.' ne correspond pas.';
            }
        }

        return ['ok' => $errors === [], 'errors' => $errors];
    }

    /**
     * @return list<string>
     */
    private function baseTables(Connection $connection): array
    {
        $rows = $connection->select('SHOW FULL TABLES WHERE Table_type = ?', ['BASE TABLE']);
        $tables = [];
        foreach ($rows as $row) {
            $values = array_values((array) $row);
            $tables[] = (string) $values[0];
        }

        sort($tables);

        return $tables;
    }

    /**
     * @param  list<string>  $tables
     * @return list<string>
     */
    private function ordered(Connection $connection, array $tables): array
    {
        $parents = array_fill_keys($tables, []);
        $database = (string) $connection->getDatabaseName();
        $links = $connection->select(
            'SELECT TABLE_NAME, REFERENCED_TABLE_NAME
             FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = ? AND REFERENCED_TABLE_NAME IS NOT NULL',
            [$database],
        );

        foreach ($links as $link) {
            $child = (string) $link->TABLE_NAME;
            $parent = (string) $link->REFERENCED_TABLE_NAME;
            if ($child !== $parent && isset($parents[$child], $parents[$parent])) {
                $parents[$child][$parent] = $parent;
            }
        }

        $ordered = [];
        $remaining = $parents;
        while ($remaining !== []) {
            $ready = [];
            foreach ($remaining as $table => $needed) {
                if (array_diff($needed, $ordered) === []) {
                    $ready[] = $table;
                }
            }
            if ($ready === []) {
                $ordered = array_merge($ordered, array_keys($remaining));
                break;
            }
            sort($ready);
            foreach ($ready as $table) {
                $ordered[] = $table;
                unset($remaining[$table]);
            }
        }

        return $ordered;
    }

    private function header(string $database, int $tables): string
    {
        return implode("\n", [
            '-- Export lecture seule pour Hostinger.',
            '-- Base source : '.$database,
            '-- Tables : '.$tables,
            '-- Ce fichier ne contient pas CREATE DATABASE ni USE.',
            'SET NAMES utf8mb4;',
            'SET FOREIGN_KEY_CHECKS=0;',
            'SET UNIQUE_CHECKS=0;',
            "SET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';",
            "SET time_zone='+00:00';",
            '',
        ]);
    }

    private function createStatement(object $row): string
    {
        $values = array_values((array) $row);
        foreach ($values as $value) {
            if (is_string($value) && str_starts_with($value, 'CREATE TABLE')) {
                return $value;
            }
        }

        throw new RuntimeException('SHOW CREATE TABLE n’a pas renvoyé de structure.');
    }

    private function literal(\PDO $pdo, mixed $value): string
    {
        if ($value === null) {
            return 'NULL';
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        if (is_int($value)) {
            return (string) $value;
        }
        if (is_float($value)) {
            if (! is_finite($value)) {
                return 'NULL';
            }

            return rtrim(rtrim(sprintf('%.10F', $value), '0'), '.');
        }
        if (is_resource($value)) {
            $value = stream_get_contents($value) ?: '';
        }

        $text = (string) $value;
        if (! mb_check_encoding($text, 'UTF-8')) {
            return "X'".bin2hex($text)."'";
        }

        $quoted = $pdo->quote($text);
        if ($quoted === false) {
            throw new RuntimeException('Impossible d’échapper une valeur pour l’export.');
        }

        return $quoted;
    }

    private function removeConfiguredSecrets(string $sql): string
    {
        $secrets = [
            config('app.key'),
            config('services.ikeepay.secret_key'),
            config('database.connections.mysql.password'),
        ];

        foreach ($secrets as $secret) {
            if (! is_string($secret) || strlen($secret) < 16) {
                continue;
            }
            $sql = str_replace($secret, '', $sql);
        }

        return $sql;
    }

    private function ident(string $name): string
    {
        return self::quoteIdent($name);
    }

    private static function quoteIdent(string $name): string
    {
        return '`'.str_replace('`', '``', $name).'`';
    }
}
