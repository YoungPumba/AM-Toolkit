<?php

namespace AMToolkit\Core;

defined('ABSPATH') || exit;

final class Installer
{
    public const SCHEMA_VERSION = '1';

    private const SCHEMA_OPTION = 'am_toolkit_db_schema_version';

    public static function activate(): void
    {
        self::installSchema();
    }

    public static function maybeUpgrade(): void
    {
        if ((string) get_option(self::SCHEMA_OPTION, '') === self::SCHEMA_VERSION) {
            return;
        }

        self::installSchema();
    }

    public static function accessGrantsTable(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'amt_access_grants';
    }

    public static function activityEventsTable(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'amt_activity_events';
    }

    private static function installSchema(): void
    {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charsetCollate = $wpdb->get_charset_collate();
        $accessTable = self::accessGrantsTable();
        $eventsTable = self::activityEventsTable();

        $accessSql = "CREATE TABLE {$accessTable} (
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
        ) {$charsetCollate};";

        $eventsSql = "CREATE TABLE {$eventsTable} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            event_key varchar(191) NOT NULL,
            event_type varchar(96) NOT NULL,
            user_id bigint(20) unsigned NOT NULL DEFAULT 0,
            actor_id bigint(20) unsigned NOT NULL DEFAULT 0,
            object_type varchar(64) NOT NULL,
            object_id bigint(20) unsigned NOT NULL DEFAULT 0,
            payload longtext NULL,
            occurred_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY event_key (event_key),
            KEY user_events (user_id, occurred_at),
            KEY object_events (object_type, object_id, occurred_at),
            KEY event_type (event_type, occurred_at)
        ) {$charsetCollate};";

        dbDelta($accessSql);
        dbDelta($eventsSql);

        $accessInstalled = $wpdb->get_var(
            $wpdb->prepare(
                'SHOW TABLES LIKE %s',
                $wpdb->esc_like($accessTable)
            )
        );
        $eventsInstalled = $wpdb->get_var(
            $wpdb->prepare(
                'SHOW TABLES LIKE %s',
                $wpdb->esc_like($eventsTable)
            )
        );

        if ($accessInstalled === $accessTable && $eventsInstalled === $eventsTable) {
            update_option(self::SCHEMA_OPTION, self::SCHEMA_VERSION, false);
        }
    }
}
