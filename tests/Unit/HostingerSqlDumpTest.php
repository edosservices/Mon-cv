<?php

namespace Tests\Unit;

use App\Support\HostingerSqlDump;
use PHPUnit\Framework\TestCase;

class HostingerSqlDumpTest extends TestCase
{
    public function test_mysql8_collation_and_definer_are_rewritten(): void
    {
        $sql = HostingerSqlDump::normalizeCreate(
            "CREATE TABLE `plans` (\n  `id` bigint unsigned NOT NULL AUTO_INCREMENT\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci"
        );

        $this->assertStringContainsString('utf8mb4_unicode_ci', $sql);
        $this->assertStringNotContainsString('utf8mb4_0900', $sql);
        $this->assertStringNotContainsString('DEFINER', HostingerSqlDump::normalizeCreate(
            'CREATE DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v` AS SELECT 1'
        ));
    }

    public function test_a_hostinger_dump_must_keep_tables_and_rows_without_mysql8_clauses(): void
    {
        $sql = <<<'SQL'
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS=0;
CREATE TABLE `plans` (
  `id` bigint unsigned NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO `plans` (`id`) VALUES (1);
SET FOREIGN_KEY_CHECKS=1;
SQL;

        $check = HostingerSqlDump::verify($sql, 1, ['plans' => 1]);

        $this->assertTrue($check['ok'], implode(' ', $check['errors']));
    }

    public function test_mysql8_only_clauses_are_rejected(): void
    {
        $sql = <<<'SQL'
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS=0;
CREATE DATABASE limete_wifi;
USE limete_wifi;
CREATE DEFINER=`root`@`localhost` TABLE `plans` (
  `id` bigint unsigned NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!999999\- enable the sandbox mode */
SET FOREIGN_KEY_CHECKS=1;
SQL;

        $check = HostingerSqlDump::verify($sql, 1, []);

        $this->assertFalse($check['ok']);
        $this->assertNotEmpty($check['errors']);
    }

    public function test_env_secret_names_are_rejected(): void
    {
        $sql = <<<'SQL'
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS=0;
CREATE TABLE `settings` (
  `id` bigint unsigned NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO `settings` (`id`) VALUES (1);
SET FOREIGN_KEY_CHECKS=1;
SQL;

        $this->assertFalse(HostingerSqlDump::verify($sql."\n-- IKPAY_SECRET_KEY\n", 1, ['settings' => 1])['ok']);
        $this->assertFalse(HostingerSqlDump::verify($sql."\n-- APP_KEY\n", 1, ['settings' => 1])['ok']);
    }

    public function test_a_real_create_database_statement_is_rejected(): void
    {
        $check = HostingerSqlDump::verify("CREATE DATABASE limete_wifi;\n", 0, []);

        $this->assertFalse($check['ok']);
        $this->assertContains('CREATE DATABASE est présent.', $check['errors']);
    }

    public function test_create_database_in_a_comment_is_accepted(): void
    {
        $sql = <<<'SQL'
-- Ce fichier ne contient pas CREATE DATABASE.
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS=0;
CREATE TABLE `plans` (
  `id` bigint unsigned NOT NULL
);
INSERT INTO `plans` (`id`) VALUES (1);
SET FOREIGN_KEY_CHECKS=1;
SQL;

        $check = HostingerSqlDump::verify($sql, 1, ['plans' => 1]);

        $this->assertTrue($check['ok'], implode(' ', $check['errors']));
    }

    public function test_create_database_inside_an_insert_value_is_accepted(): void
    {
        $sql = <<<'SQL'
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS=0;
CREATE TABLE `settings` (
  `id` bigint unsigned NOT NULL,
  `value` text
);
INSERT INTO `settings` (`id`, `value`)
VALUES (1, 'CREATE DATABASE limete_wifi');
SET FOREIGN_KEY_CHECKS=1;
SQL;

        $check = HostingerSqlDump::verify($sql, 1, ['settings' => 1]);

        $this->assertTrue($check['ok'], implode(' ', $check['errors']));
    }

    public function test_a_real_use_statement_is_rejected(): void
    {
        $check = HostingerSqlDump::verify("USE limete_wifi;\n", 0, []);

        $this->assertFalse($check['ok']);
        $this->assertContains('USE est présent.', $check['errors']);
    }

    public function test_use_inside_an_insert_value_is_accepted(): void
    {
        $sql = <<<'SQL'
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS=0;
CREATE TABLE `settings` (
  `id` bigint unsigned NOT NULL,
  `value` text
);
INSERT INTO `settings` (`id`, `value`)
VALUES (1, 'USE limete_wifi');
SET FOREIGN_KEY_CHECKS=1;
SQL;

        $check = HostingerSqlDump::verify($sql, 1, ['settings' => 1]);

        $this->assertTrue($check['ok'], implode(' ', $check['errors']));
    }
}
