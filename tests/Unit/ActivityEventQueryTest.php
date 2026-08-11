<?php

declare(strict_types=1);

use AMToolkit\Core\Diagnostics\ActivityEventQuery;
use PHPUnit\Framework\TestCase;

final class ActivityEventQueryTest extends TestCase
{
    public function testInvalidAndEmptyFiltersAreDiscarded(): void
    {
        $query = new ActivityEventQuery(
            requestId: 'customer@example.com',
            userId: 0,
            objectType: ' !!! ',
            objectId: -10,
            eventType: ' !!! ',
            limit: 0
        );

        self::assertNull($query->requestId());
        self::assertNull($query->userId());
        self::assertNull($query->objectType());
        self::assertNull($query->objectId());
        self::assertNull($query->eventType());
        self::assertSame(1, $query->limit());
    }

    public function testValidFiltersAreNormalizedAndLimitIsCapped(): void
    {
        $query = new ActivityEventQuery(
            requestId: '  am-20260810-abcdef123456  ',
            userId: 12,
            objectType: 'Course Item',
            objectId: 91,
            eventType: 'Course.Progressed',
            limit: 500
        );

        self::assertSame('AM-20260810-ABCDEF123456', $query->requestId());
        self::assertSame(12, $query->userId());
        self::assertSame('courseitem', $query->objectType());
        self::assertSame(91, $query->objectId());
        self::assertSame('course.progressed', $query->eventType());
        self::assertSame(200, $query->limit());
    }
}
