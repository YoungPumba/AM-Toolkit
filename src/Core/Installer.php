<?php

namespace AMToolkit\Core;

use AMToolkit\Modules\Access\AccessSchema;
use AMToolkit\Modules\Access\Migrations\CreateAccessTables;
use AMToolkit\Modules\Access\Migrations\UpgradeActivityEventContract;
use AMToolkit\Modules\Courses\Migrations\CreateCoursesCatalogTables;
use AMToolkit\Modules\Courses\Migrations\CreateCoursesProgressTables;
use AMToolkit\Modules\Courses\Migrations\CreateCourseProductMappingsTable;
use AMToolkit\Modules\Courses\Migrations\CreateCourseMeetingsTables;
use AMToolkit\Modules\Courses\Migrations\UpgradeCoursesProgressSources;

defined('ABSPATH') || exit;

final class Installer
{
    public const SCHEMA_VERSION = '2';

    public static function activate(): void
    {
        self::install(true);
    }

    public static function maybeUpgrade(): void
    {
        self::install(false);
    }

    /** @deprecated Use AccessSchema::grantsTable(). */
    public static function accessGrantsTable(): string
    {
        return AccessSchema::grantsTable();
    }

    /** @deprecated Use AccessSchema::eventsTable(). */
    public static function activityEventsTable(): string
    {
        return AccessSchema::eventsTable();
    }

    private static function install(bool $failHard): void
    {
        try {
            $runner = new MigrationRunner();
            $runner->run('access', [
                1 => new CreateAccessTables(),
                2 => new UpgradeActivityEventContract(),
            ]);
            $runner->run('courses', [
                1 => new CreateCoursesCatalogTables(),
                2 => new CreateCoursesProgressTables(),
                3 => new CreateCourseProductMappingsTable(),
                4 => new UpgradeCoursesProgressSources(),
                5 => new CreateCourseMeetingsTables(),
            ]);

            Capabilities::install();
        } catch (\Throwable $error) {
            error_log('[AM Toolkit] Installation failed: ' . $error->getMessage());

            if ($failHard) {
                throw $error;
            }
        }
    }
}
