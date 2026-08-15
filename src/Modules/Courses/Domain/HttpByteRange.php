<?php

namespace AMToolkit\Modules\Courses\Domain;

defined('ABSPATH') || exit;

final class HttpByteRange
{
    private function __construct(
        private int $start,
        private int $end,
        private int $resourceSize,
        private bool $partial
    ) {
    }

    public static function fromHeader(?string $header, int $resourceSize): self|\WP_Error
    {
        if ($resourceSize < 0) {
            return self::invalid();
        }

        if ($header === null || trim($header) === '') {
            return new self(0, max(0, $resourceSize - 1), $resourceSize, false);
        }

        if ($resourceSize === 0 || !preg_match('/^bytes=(\d*)-(\d*)$/', trim($header), $matches)) {
            return self::invalid();
        }

        $startText = $matches[1];
        $endText = $matches[2];

        if ($startText === '' && $endText === '') {
            return self::invalid();
        }

        if ($startText === '') {
            $suffixLength = (int) $endText;

            if ($suffixLength <= 0) {
                return self::invalid();
            }

            $start = max(0, $resourceSize - $suffixLength);
            $end = $resourceSize - 1;
        } else {
            $start = (int) $startText;
            $end = $endText === '' ? $resourceSize - 1 : (int) $endText;

            if ($start >= $resourceSize || $end < $start) {
                return self::invalid();
            }

            $end = min($end, $resourceSize - 1);
        }

        return new self($start, $end, $resourceSize, true);
    }

    public function start(): int
    {
        return $this->start;
    }

    public function end(): int
    {
        return $this->end;
    }

    public function length(): int
    {
        return $this->resourceSize === 0 ? 0 : ($this->end - $this->start + 1);
    }

    public function resourceSize(): int
    {
        return $this->resourceSize;
    }

    public function isPartial(): bool
    {
        return $this->partial;
    }

    private static function invalid(): \WP_Error
    {
        return new \WP_Error(
            'am_toolkit_invalid_http_range',
            __('Żądany fragment pliku jest niedostępny.', 'am-toolkit')
        );
    }
}
