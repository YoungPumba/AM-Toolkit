<?php

namespace AMToolkit\Modules\Courses\Domain;

defined('ABSPATH') || exit;

final class HttpRangeRequestHeader
{
    private function __construct(
        private ?string $value,
        private string $source
    ) {
    }

    /**
     * @param array<string, mixed> $server
     * @param array<string, mixed> $headers
     */
    public static function fromRequest(array $server, array $headers = []): self
    {
        $serverCandidates = [
            'HTTP_RANGE' => 'http_range',
            'REDIRECT_HTTP_RANGE' => 'redirect_http_range',
        ];

        foreach ($serverCandidates as $key => $source) {
            $value = self::candidate($server[$key] ?? null);

            if ($value !== null) {
                return new self($value, $source);
            }
        }

        foreach ($headers as $name => $headerValue) {
            if (strcasecmp($name, 'Range') !== 0) {
                continue;
            }

            $value = self::candidate($headerValue);

            if ($value !== null) {
                return new self($value, 'headers');
            }
        }

        return new self(null, 'missing');
    }

    public function value(): ?string
    {
        return $this->value;
    }

    public function source(): string
    {
        return $this->source;
    }

    public function isPresent(): bool
    {
        return $this->value !== null;
    }

    private static function candidate(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
