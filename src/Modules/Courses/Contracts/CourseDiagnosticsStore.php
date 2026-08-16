<?php

namespace AMToolkit\Modules\Courses\Contracts;

defined('ABSPATH') || exit;

interface CourseDiagnosticsStore
{
    /** @return array<string, mixed>|\WP_Error */
    public function schemaHealth(): array|\WP_Error;

    /** @return array<string, mixed>|\WP_Error */
    public function snapshot(int $userId, int $courseId): array|\WP_Error;
}
