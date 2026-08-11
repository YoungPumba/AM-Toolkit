<?php

declare(strict_types=1);

use AMToolkit\Core\Diagnostics\DomainEvent;
use PHPUnit\Framework\TestCase;

final class DomainEventTest extends TestCase
{
    public function testAccessEventKeepsOnlyTheDocumentedPayloadFields(): void
    {
        $event = DomainEvent::create(
            'access.granted.test',
            'access.granted',
            12,
            4,
            'course',
            91,
            [
                'grant_id' => 8,
                'source_type' => 'order',
                'source_id' => 120,
                'email' => 'secret@example.com',
            ],
            '2026-08-10 10:00:00',
            'AM-20260810-ABCDEF123456'
        );

        $record = $event->toRecord();

        self::assertSame(DomainEvent::SCHEMA_VERSION, $record['schema_version']);
        self::assertSame('AM-20260810-ABCDEF123456', $record['request_id']);
        self::assertSame([
            'grant_id' => 8,
            'source_type' => 'order',
            'source_id' => 120,
        ], $record['payload']);
    }

    public function testEventRequiresStableIdentityFields(): void
    {
        $this->expectException(InvalidArgumentException::class);

        DomainEvent::create(
            '',
            'course.progressed',
            12,
            4,
            'course',
            91,
            [],
            '2026-08-10 10:00:00'
        );
    }

    public function testPayloadDepthIsBounded(): void
    {
        $this->expectException(LengthException::class);

        DomainEvent::create(
            'course.progressed.test',
            'course.progressed',
            12,
            4,
            'course',
            91,
            ['one' => ['two' => ['three' => ['four' => true]]]],
            '2026-08-10 10:00:00'
        );
    }

    public function testTimestampMustUseTheExactUtcMysqlFormat(): void
    {
        $this->expectException(InvalidArgumentException::class);

        DomainEvent::create(
            'course.progressed.invalid-time',
            'course.progressed',
            12,
            4,
            'course',
            91,
            [],
            '2026-08-10T10:00:00+02:00'
        );
    }

    public function testUnsupportedPayloadValuesAreRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        DomainEvent::create(
            'course.progressed.object',
            'course.progressed',
            12,
            4,
            'course',
            91,
            ['unsafe' => new stdClass()],
            '2026-08-10 10:00:00'
        );
    }

    public function testNestedPayloadFieldCountIsBounded(): void
    {
        $this->expectException(LengthException::class);

        DomainEvent::create(
            'course.progressed.too-many-fields',
            'course.progressed',
            12,
            4,
            'course',
            91,
            ['nested' => array_fill(0, 25, true)],
            '2026-08-10 10:00:00'
        );
    }
}
