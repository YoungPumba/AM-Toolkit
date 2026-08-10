<?php

namespace AMToolkit\Modules\Access;

defined('ABSPATH') || exit;

final class AccessSchema
{
    public static function grantsTable(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'amt_access_grants';
    }

    public static function eventsTable(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'amt_activity_events';
    }
}
