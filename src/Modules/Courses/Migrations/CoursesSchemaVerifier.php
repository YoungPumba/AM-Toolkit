<?php

namespace AMToolkit\Modules\Courses\Migrations;

defined('ABSPATH') || exit;

final class CoursesSchemaVerifier
{
    /** @param array<string, list<string>> $requirements */
    public function verify(array $requirements): bool
    {
        foreach ($requirements as $table => $indexes) {
            if (! $this->tableExists($table)) {
                return false;
            }

            foreach ($indexes as $index) {
                if (! $this->indexExists($table, $index)) {
                    return false;
                }
            }
        }

        return true;
    }

    private function tableExists(string $table): bool
    {
        global $wpdb;

        return $wpdb->get_var(
            $wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table))
        ) === $table;
    }

    private function indexExists(string $table, string $index): bool
    {
        global $wpdb;

        return $wpdb->get_var(
            $wpdb->prepare("SHOW INDEX FROM {$table} WHERE Key_name = %s", $index),
            2
        ) === $index;
    }
}
