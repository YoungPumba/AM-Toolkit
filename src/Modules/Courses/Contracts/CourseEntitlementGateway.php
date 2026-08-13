<?php

namespace AMToolkit\Modules\Courses\Contracts;

defined('ABSPATH') || exit;

interface CourseEntitlementGateway
{
    /** @param array<string, mixed> $context */
    public function grant(int $userId, int $courseId, array $context): int|\WP_Error;

    /** @param array{request_id?: string} $context */
    public function revokeAllSource(
        string $sourceType,
        int $sourceId,
        array $context
    ): int|\WP_Error;
}
