<?php

namespace AMToolkit\Core;

defined('ABSPATH') || exit;

final class MigrationRunner
{
    private const OPTION_PREFIX = 'am_toolkit_schema_';

    /** @param array<int, MigrationInterface> $migrations */
    public function run(string $moduleId, array $migrations): void
    {
        $moduleId = sanitize_key($moduleId);

        if ($moduleId === '') {
            throw new \InvalidArgumentException('Migration module ID cannot be empty.');
        }

        ksort($migrations, SORT_NUMERIC);
        $option = self::OPTION_PREFIX . $moduleId;
        $current = max(0, (int) get_option($option, 0));

        foreach ($migrations as $version => $migration) {
            $version = (int) $version;

            if ($version <= $current) {
                continue;
            }

            if ($version !== $current + 1) {
                throw new \LogicException(
                    "Missing {$moduleId} migration version " . ($current + 1)
                );
            }

            if (!$migration->up()) {
                throw new \RuntimeException(
                    "Migration {$moduleId}:{$version} did not verify successfully."
                );
            }

            update_option($option, $version, false);
            $current = $version;
            do_action('am_toolkit_migration_completed', $moduleId, $version);
        }
    }
}
