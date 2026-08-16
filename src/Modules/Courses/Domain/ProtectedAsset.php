<?php

namespace AMToolkit\Modules\Courses\Domain;

defined('ABSPATH') || exit;

final class ProtectedAsset
{
    public function __construct(
        private string $path,
        private string $mimeType,
        private string $downloadName,
        private int $size
    ) {
        if ($path === '' || $mimeType === '' || $downloadName === '' || $size < 0) {
            throw new \InvalidArgumentException('Protected asset metadata is invalid.');
        }
    }

    public function path(): string
    {
        return $this->path;
    }

    public function mimeType(): string
    {
        return $this->mimeType;
    }

    public function downloadName(): string
    {
        return $this->downloadName;
    }

    public function size(): int
    {
        return $this->size;
    }
}
