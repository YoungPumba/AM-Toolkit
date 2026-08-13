<?php

namespace AMToolkit\Modules\Courses;

use AMToolkit\Modules\Courses\Contracts\MigrationCheckpointStore;

defined('ABSPATH') || exit;

final class WpOptionMigrationCheckpointStore implements MigrationCheckpointStore
{
    private const OPTION = 'am_toolkit_courses_purchase_backfill';

    public function load(): array
    {
        $checkpoint = get_option(self::OPTION, []);

        if (!is_array($checkpoint)) {
            return ['page' => 1, 'completed' => false];
        }

        return [
            'page' => max(1, absint($checkpoint['page'] ?? 1)),
            'completed' => (bool) ($checkpoint['completed'] ?? false),
        ];
    }

    public function save(int $nextPage, bool $completed): bool
    {
        return update_option(
            self::OPTION,
            ['page' => max(1, $nextPage), 'completed' => $completed],
            false
        );
    }
}
