<?php

namespace AMToolkit\Modules\Courses\Contracts;

defined('ABSPATH') || exit;

interface CourseVideoRenderer
{
    /** @param array<string, mixed> $context */
    public function render(string $sourceUrl, array $context = []): string|\WP_Error;
}
