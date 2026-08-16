<?php

namespace AMToolkit\Modules\Courses\Migrations;

use AMToolkit\Core\MigrationInterface;
use AMToolkit\Modules\Courses\CoursesSchema;

defined('ABSPATH') || exit;

final class CreateLessonTaskTables implements MigrationInterface
{
    public function up(): bool
    {
        global $wpdb;

        if (!function_exists('dbDelta')) {
            $upgradeFile = ABSPATH . 'wp-admin/includes/upgrade.php';

            if (!is_file($upgradeFile)) {
                return false;
            }

            require_once $upgradeFile;
        }

        dbDelta(array_values(CoursesSchema::lessonTaskDefinitions($wpdb->get_charset_collate())));

        return (new CoursesSchemaVerifier())->verify([
            CoursesSchema::lessonTasksTable() => ['public_id', 'lesson_task_order'],
            CoursesSchema::lessonTaskProgressTable() => ['user_task', 'lesson_task_progress'],
        ]);
    }
}
