<?php

namespace AMToolkit\Modules\Courses\Migrations;

use AMToolkit\Core\MigrationInterface;
use AMToolkit\Modules\Courses\CoursesSchema;

defined('ABSPATH') || exit;

final class CreateCourseMeetingsTables implements MigrationInterface
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

        $catalog = CoursesSchema::catalogDefinitions($wpdb->get_charset_collate());
        $definitions = CoursesSchema::meetingDefinitions($wpdb->get_charset_collate());
        dbDelta([$catalog[CoursesSchema::coursesTable()], ...array_values($definitions)]);

        return (new CoursesSchemaVerifier())->verify([
            CoursesSchema::coursesTable() => ['public_id'],
            CoursesSchema::meetingsTable() => ['public_id', 'course_schedule'],
            CoursesSchema::meetingRevisionsTable() => ['meeting_revision', 'course_revisions'],
        ]);
    }
}
