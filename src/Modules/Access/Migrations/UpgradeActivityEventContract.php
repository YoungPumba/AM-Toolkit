<?php

namespace AMToolkit\Modules\Access\Migrations;

use AMToolkit\Core\MigrationInterface;
use AMToolkit\Modules\Access\AccessSchema;

defined('ABSPATH') || exit;

final class UpgradeActivityEventContract implements MigrationInterface
{
    public function up(): bool
    {
        global $wpdb;

        if (! function_exists('dbDelta')) {
            $upgradeFile = ABSPATH . 'wp-admin/includes/upgrade.php';

            if (! is_file($upgradeFile)) {
                return false;
            }

            require_once $upgradeFile;
        }

        $table = AccessSchema::eventsTable();
        $charsetCollate = $wpdb->get_charset_collate();

        dbDelta("CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            event_key varchar(191) NOT NULL,
            event_type varchar(96) NOT NULL,
            user_id bigint(20) unsigned NOT NULL DEFAULT 0,
            actor_id bigint(20) unsigned NOT NULL DEFAULT 0,
            object_type varchar(64) NOT NULL,
            object_id bigint(20) unsigned NOT NULL DEFAULT 0,
            schema_version smallint(5) unsigned NOT NULL DEFAULT 1,
            request_id varchar(64) NOT NULL DEFAULT '',
            payload longtext NULL,
            occurred_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY event_key (event_key),
            KEY user_events (user_id, occurred_at),
            KEY object_events (object_type, object_id, occurred_at),
            KEY event_type (event_type, occurred_at),
            KEY request_events (request_id, occurred_at)
        ) {$charsetCollate};");

        if (
            ! $this->columnExists($table, 'schema_version')
            || ! $this->columnExists($table, 'request_id')
        ) {
            return false;
        }

        /*
         * Zdarzenia zapisane przed wprowadzeniem kontraktu request_id nie
         * zawierają informacji pozwalającej odtworzyć rzeczywiste żądanie.
         * Nadajemy im więc stabilny identyfikator techniczny wyliczony z
         * istniejącego rekordu. Ponowne uruchomienie migracji da ten sam wynik.
         */
        $backfilled = $wpdb->query(
            "UPDATE {$table}
            SET request_id = CONCAT(
                'AM-',
                DATE_FORMAT(occurred_at, '%Y%m%d'),
                '-',
                UPPER(SUBSTRING(MD5(CONCAT('legacy:', id, ':', event_key)), 1, 12))
            )
            WHERE request_id = ''"
        );

        if ($backfilled === false) {
            return false;
        }

        return (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$table} WHERE request_id = ''"
        ) === 0;
    }

    private function columnExists(string $table, string $column): bool
    {
        global $wpdb;

        return $wpdb->get_var(
            $wpdb->prepare("SHOW COLUMNS FROM {$table} LIKE %s", $column)
        ) === $column;
    }
}
