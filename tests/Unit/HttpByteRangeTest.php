<?php

declare(strict_types=1);

namespace AMToolkit\Tests\Unit;

use AMToolkit\Modules\Courses\Domain\HttpByteRange;
use PHPUnit\Framework\TestCase;

final class HttpByteRangeTest extends TestCase
{
    public function testMissingHeaderReturnsWholeResource(): void
    {
        $range = HttpByteRange::fromHeader(null, 1000);

        self::assertInstanceOf(HttpByteRange::class, $range);
        self::assertFalse($range->isPartial());
        self::assertSame(0, $range->start());
        self::assertSame(999, $range->end());
        self::assertSame(1000, $range->length());
    }

    public function testExplicitOpenAndSuffixRangesAreNormalized(): void
    {
        $explicit = HttpByteRange::fromHeader('bytes=100-199', 1000);
        $open = HttpByteRange::fromHeader('bytes=900-', 1000);
        $suffix = HttpByteRange::fromHeader('bytes=-50', 1000);

        self::assertInstanceOf(HttpByteRange::class, $explicit);
        self::assertSame([100, 199, 100], [$explicit->start(), $explicit->end(), $explicit->length()]);
        self::assertInstanceOf(HttpByteRange::class, $open);
        self::assertSame([900, 999, 100], [$open->start(), $open->end(), $open->length()]);
        self::assertInstanceOf(HttpByteRange::class, $suffix);
        self::assertSame([950, 999, 50], [$suffix->start(), $suffix->end(), $suffix->length()]);
    }

    /** @dataProvider invalidRanges */
    public function testInvalidOrMultipleRangesAreRejected(string $header): void
    {
        $range = HttpByteRange::fromHeader($header, 1000);

        self::assertInstanceOf(\WP_Error::class, $range);
        self::assertSame('am_toolkit_invalid_http_range', $range->get_error_code());
    }

    /** @return list<array{string}> */
    public function invalidRanges(): array
    {
        return [
            ['items=0-10'],
            ['bytes=1000-'],
            ['bytes=200-100'],
            ['bytes=0-10,20-30'],
            ['bytes=-0'],
        ];
    }
}
