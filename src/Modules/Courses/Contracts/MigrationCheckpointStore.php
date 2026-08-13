<?php

namespace AMToolkit\Modules\Courses\Contracts;

defined('ABSPATH') || exit;

interface MigrationCheckpointStore
{
    /** @return array{page: int, completed: bool} */
    public function load(): array;

    public function save(int $nextPage, bool $completed): bool;
}
