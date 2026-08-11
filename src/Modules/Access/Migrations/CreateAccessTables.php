<?php

namespace AMToolkit\Modules\Access\Migrations;

use AMToolkit\Core\MigrationInterface;
use AMToolkit\Modules\Access\AccessSchema;

defined('ABSPATH') || exit;

final class CreateAccessTables implements MigrationInterface
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

        $charsetCollate = $wpdb->get_charset_collate();
        $accessTable = AccessSchema::grantsTable();
        $eventsTable = AccessSchema::eventsTable();

        dbDelta("CREATE TABLE {$accessTable} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            resource_type varchar(64) NOT NULL,
            resource_id bigint(20) unsigned NOT NULL,
            grant_key varchar(191) NOT NULL,
            source_type varchar(64) NOT NULL,
            source_id bigint(20) unsigned NOT NULL DEFAULT 0,
            status varchar(24) NOT NULL DEFAULT 'active',
            starts_at datetime NULL DEFAULT NULL,
            expires_at datetime NULL DEFAULT NULL,
            granted_at datetime NOT NULL,
            revoked_at datetime NULL DEFAULT NULL,
            metadata longtext NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY grant_key (grant_key),
            KEY access_lookup (user_id, resource_type, resource_id, status),
            KEY source_lookup (source_type, source_id),
            KEY expiry_lookup (status, expires_at)
        ) {$charsetCollate};");

        dbDelta("CREATE TABLE {$eventsTable} (
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

        return $this->tableExists($accessTable)
            && $this->tableExists($eventsTable);
    }

    private function tableExists(string $table): bool
    {
        global $wpdb;

        return $wpdb->get_var(
            $wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table))
        ) === $table;
    }
}
