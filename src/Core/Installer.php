<?php

namespace AMToolkit\Core;

use AMToolkit\Modules\Access\AccessSchema;
use AMToolkit\Modules\Access\Migrations\CreateAccessTables;

defined('ABSPATH') || exit;

final class Installer
{
    public const SCHEMA_VERSION = '1';

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
