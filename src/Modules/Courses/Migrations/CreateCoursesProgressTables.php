<?php

namespace AMToolkit\Modules\Courses\Migrations;

use AMToolkit\Core\MigrationInterface;
use AMToolkit\Modules\Courses\CoursesSchema;

defined('ABSPATH') || exit;

final class CreateCoursesProgressTables implements MigrationInterface
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

        foreach (CoursesSchema::progressDefinitions($wpdb->get_charset_collate()) as $sql) {
            dbDelta($sql);
        }

        return (new CoursesSchemaVerifier())->verify([
            CoursesSchema::progressTable() => ['user_course_lesson'],
            CoursesSchema::completionsTable() => ['user_course_program'],
        ]);
    }
}
