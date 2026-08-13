<?php

namespace AMToolkit\Modules\Courses\Migrations;

use AMToolkit\Core\MigrationInterface;
use AMToolkit\Modules\Courses\CoursesSchema;

defined('ABSPATH') || exit;

final class CreateCourseProductMappingsTable implements MigrationInterface
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
        dbDelta(CoursesSchema::productMappingDefinition($wpdb->get_charset_collate()));

        return (new CoursesSchemaVerifier())->verify([
            CoursesSchema::productMappingsTable() => ['product_course', 'active_product'],
        ]);
    }
}
