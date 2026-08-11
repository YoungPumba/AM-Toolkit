<?php

declare(strict_types=1);

use AMToolkit\Core\Diagnostics\RequestId;
use PHPUnit\Framework\TestCase;

final class RequestIdTest extends TestCase
{
    public function testGeneratedIdentifierMatchesThePublicContract(): void
    {
        self::assertMatchesRegularExpression(
            '/^AM-[0-9]{8}-[A-F0-9]{12}$/',
            RequestId::generate()
        );
    }

    public function testValidIdentifierIsNormalizedWithoutChangingItsIdentity(): void
    {
        self::assertSame(
            'AM-20260810-ABCDEF123456',
            RequestId::normalize(' am-20260810-abcdef123456 ')
        );
    }

    public function testInvalidIdentifierIsReplaced(): void
    {
        $requestId = RequestId::normalize('customer-email@example.com');

        self::assertTrue(RequestId::isValid($requestId));
        self::assertStringNotContainsString('@', $requestId);
    }
}
