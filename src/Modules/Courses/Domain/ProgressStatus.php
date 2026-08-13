<?php

namespace AMToolkit\Modules\Courses\Domain;

defined('ABSPATH') || exit;

final class ProgressStatus
{
    public const STARTED = 'started';
    public const COMPLETED = 'completed';

    public static function assertValid(string $status): void
    {
        if (! in_array($status, [self::STARTED, self::COMPLETED], true)) {
            throw new \InvalidArgumentException("Unsupported lesson progress status: {$status}");
        }
    }
}
