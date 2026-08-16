<?php

namespace AMToolkit\Modules\Courses\Migrations;

use AMToolkit\Core\MigrationInterface;
use AMToolkit\Modules\Courses\CoursesSchema;

defined('ABSPATH') || exit;

final class CreateCourseQaTable implements MigrationInterface
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

        dbDelta(array_values(CoursesSchema::qaDefinitions($wpdb->get_charset_collate())));

        return (new CoursesSchemaVerifier())->verify([
            CoursesSchema::qaEntriesTable() => ['public_id', 'course_qa_order', 'lesson_qa_context'],
        ]);
    }
}
