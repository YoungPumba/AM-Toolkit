<?php

namespace AMToolkit\Modules\Courses\Contracts;

use AMToolkit\Modules\Courses\Domain\ProtectedAsset;

defined('ABSPATH') || exit;

/**
 * Replaceable storage boundary for private course files.
 * References are opaque identifiers, never public URLs.
 */
interface CourseAssetStore
{
    public function provider(): string;

    /** @param array<string, mixed> $upload */
    public function storeUpload(array $upload, string $kind): string|\WP_Error;

    public function locate(string $reference, string $downloadName): ProtectedAsset|\WP_Error;

    public function remove(string $reference): bool;
}
