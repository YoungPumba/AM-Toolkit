<?php

namespace AMToolkit\Modules\Courses\Contracts;

defined('ABSPATH') || exit;

interface ProgressiveCourseVideoStore
{
    public function videoSupportsProgressiveDownload(string $reference): bool|\WP_Error;
}
