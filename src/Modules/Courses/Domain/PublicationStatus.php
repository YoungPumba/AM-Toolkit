<?php

namespace AMToolkit\Modules\Courses\Domain;

defined('ABSPATH') || exit;

final class PublicationStatus
{
    public const DRAFT = 'draft';
    public const PUBLISHED = 'published';
    public const ARCHIVED = 'archived';

    public static function assertValid(string $status): void
    {
        if (! in_array($status, self::all(), true)) {
            throw new \InvalidArgumentException("Unsupported publication status: {$status}");
        }
    }

    /** @return list<string> */
    public static function all(): array
    {
        return [self::DRAFT, self::PUBLISHED, self::ARCHIVED];
    }
}
