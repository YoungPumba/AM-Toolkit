<?php

namespace AMToolkit\Modules\Courses\Migrations;

use AMToolkit\Core\MigrationInterface;
use AMToolkit\Modules\Courses\CoursesSchema;

defined('ABSPATH') || exit;

final class CreateCoursesCatalogTables implements MigrationInterface
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

        foreach (CoursesSchema::catalogDefinitions($wpdb->get_charset_collate()) as $sql) {
            dbDelta($sql);
        }

        return (new CoursesSchemaVerifier())->verify([
            CoursesSchema::coursesTable() => ['public_id'],
            CoursesSchema::programVersionsTable() => ['public_id', 'course_version'],
            CoursesSchema::sectionsTable() => ['public_id', 'program_position'],
            CoursesSchema::lessonsTable() => ['public_id'],
            CoursesSchema::programLessonsTable() => ['program_lesson'],
            CoursesSchema::materialsTable() => ['public_id'],
        ]);
    }
}
