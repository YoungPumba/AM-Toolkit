<?php

namespace AMToolkit\Core\Diagnostics;

defined('ABSPATH') || exit;

final class RequestId
{
    private const PREFIX = 'AM';

    private const PATTERN = '/^AM-[0-9]{8}-[A-F0-9]{12}$/';

    public static function generate(): string
    {
        return sprintf(
            '%s-%s-%s',
            self::PREFIX,
            gmdate('Ymd'),
            strtoupper(bin2hex(random_bytes(6)))
        );
    }

    public static function normalize(?string $requestId): string
    {
        $requestId = strtoupper(trim((string) $requestId));

        return self::isValid($requestId) ? $requestId : self::generate();
    }

    public static function isValid(string $requestId): bool
    {
        return preg_match(self::PATTERN, $requestId) === 1;
    }
}
