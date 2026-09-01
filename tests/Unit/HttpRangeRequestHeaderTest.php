<?php

declare(strict_types=1);

namespace AMToolkit\Tests\Unit;

use AMToolkit\Modules\Courses\Domain\HttpByteRange;
use AMToolkit\Modules\Courses\Domain\HttpRangeRequestHeader;
use PHPUnit\Framework\TestCase;

final class HttpRangeRequestHeaderTest extends TestCase
{
    public function testItUsesTheStandardCgiVariableFirst(): void
    {
        $header = HttpRangeRequestHeader::fromRequest(
            [
                'HTTP_RANGE' => 'bytes=10-19',
                'REDIRECT_HTTP_RANGE' => 'bytes=20-29',
            ],
            ['Range' => 'bytes=30-39']
        );

        self::assertTrue($header->isPresent());
        self::assertSame('bytes=10-19', $header->value());
        self::assertSame('http_range', $header->source());
    }

    public function testItRecoversTheHeaderFromARewriteVariable(): void
    {
        $header = HttpRangeRequestHeader::fromRequest([
            'REDIRECT_HTTP_RANGE' => ' bytes=100- ',
        ]);

        self::assertTrue($header->isPresent());
        self::assertSame('bytes=100-', $header->value());
        self::assertSame('redirect_http_range', $header->source());
    }

    public function testItRecoversCaseInsensitiveRequestHeaders(): void
    {
        $header = HttpRangeRequestHeader::fromRequest([], [
            'rAnGe' => 'bytes=0-1',
        ]);

        self::assertTrue($header->isPresent());
        self::assertSame('bytes=0-1', $header->value());
        self::assertSame('headers', $header->source());
    }

    public function testItReportsAMissingOrInvalidScalarHeaderWithoutInventingARange(): void
    {
        $header = HttpRangeRequestHeader::fromRequest(
            ['HTTP_RANGE' => ['bytes=0-1']],
            ['Range' => '   ']
        );

        self::assertFalse($header->isPresent());
        self::assertNull($header->value());
        self::assertSame('missing', $header->source());
    }

    public function testARecoveredRewriteHeaderProducesAPartialByteRange(): void
    {
        $header = HttpRangeRequestHeader::fromRequest([
            'REDIRECT_HTTP_RANGE' => 'bytes=0-1',
        ]);
        $range = HttpByteRange::fromHeader($header->value(), 411286529, 134217728);

        self::assertTrue($range->isPartial());
        self::assertSame(0, $range->start());
        self::assertSame(1, $range->end());
        self::assertSame(2, $range->length());
    }
}
