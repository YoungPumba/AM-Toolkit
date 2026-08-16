<?php

namespace AMToolkit\Modules\Courses\Domain;

defined('ABSPATH') || exit;

final class MeetingStatus
{
    public const SCHEDULED = 'scheduled';
    public const RESCHEDULED = 'rescheduled';
    public const CANCELLED = 'cancelled';
    public const COMPLETED = 'completed';

    /** @return list<string> */
    public static function all(): array
    {
        return [self::SCHEDULED, self::RESCHEDULED, self::CANCELLED, self::COMPLETED];
    }

    public static function assertValid(string $status): void
    {
        if (!in_array($status, self::all(), true)) {
            throw new \InvalidArgumentException('Meeting status is invalid.');
        }
    }
}
