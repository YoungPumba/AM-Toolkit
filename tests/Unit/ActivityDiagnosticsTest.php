<?php

declare(strict_types=1);

use AMToolkit\Core\Diagnostics\ActivityDiagnostics;
use AMToolkit\Core\Diagnostics\ActivityEventQuery;
use AMToolkit\Core\Diagnostics\DomainEvent;
use AMToolkit\Modules\Access\ActivityEventStore;
use PHPUnit\Framework\TestCase;

final class ActivityDiagnosticsTest extends TestCase
{
    public function testInspectionDetectsContractViolations(): void
    {
        $store = new MemoryActivityEvents([
            $this->event('same-key', 'AM-20260810-ABCDEF123456', 1),
            $this->event('same-key', 'invalid', 99),
        ]);
        $diagnostics = new ActivityDiagnostics($store);

        $result = $diagnostics->inspect(new ActivityEventQuery(limit: 50));

        self::assertIsArray($result);
        self::assertFalse($result['valid']);
        self::assertSame(['same-key'], $result['duplicate_event_keys']);
        self::assertSame(1, $result['invalid_request_ids']);
        self::assertSame(1, $result['unsupported_schema_versions']);
    }

    public function testExportPseudonymizesUserIdentifiers(): void
    {
        $store = new MemoryActivityEvents([
            $this->event('access.granted.test', 'AM-20260810-ABCDEF123456', 1),
        ]);
        $diagnostics = new ActivityDiagnostics($store);

        $json = $diagnostics->export(new ActivityEventQuery(limit: 10));

        self::assertIsString($json);
        self::assertStringNotContainsString('"user_id"', $json);
        self::assertStringNotContainsString('"actor_id"', $json);
        self::assertStringContainsString('"user_ref"', $json);
        self::assertStringContainsString('"request_id"', $json);
    }

    public function testInspectionDetectsMissingKeysAndInvalidTimestamps(): void
    {
        $event = $this->event('', 'AM-20260810-ABCDEF123456', 1);
        $event['occurred_at'] = 'not-a-timestamp';

        $diagnostics = new ActivityDiagnostics(new MemoryActivityEvents([$event]));
        $result = $diagnostics->inspect(new ActivityEventQuery(limit: 10));

        self::assertIsArray($result);
        self::assertFalse($result['valid']);
        self::assertSame(1, $result['missing_event_keys']);
        self::assertSame(1, $result['invalid_timestamps']);
        self::assertSame([], $result['duplicate_event_keys']);
    }

    public function testExportContainsOnlySafePayloadMetadata(): void
    {
        $event = $this->event('course.progressed.safe-export', 'AM-20260810-ABCDEF123456', 1);
        $event['payload'] = [
            'lesson_id' => 44,
            'email' => 'secret@example.com',
            'token' => 'private-token',
        ];

        $diagnostics = new ActivityDiagnostics(new MemoryActivityEvents([$event]));
        $json = $diagnostics->export(new ActivityEventQuery(limit: 10));

        self::assertIsString($json);
        self::assertStringContainsString('"export_version": 2', $json);
        self::assertStringContainsString('"payload_keys"', $json);
        self::assertStringContainsString('"lesson_id"', $json);
        self::assertStringNotContainsString('secret@example.com', $json);
        self::assertStringNotContainsString('private-token', $json);
        self::assertStringNotContainsString('"payload":', $json);
    }

    /** @return array<string, mixed> */
    private function event(string $eventKey, string $requestId, int $schemaVersion): array
    {
        return [
            'id' => 1,
            'event_key' => $eventKey,
            'event_type' => 'access.granted',
            'schema_version' => $schemaVersion,
            'request_id' => $requestId,
            'user_id' => 12,
            'actor_id' => 4,
            'object_type' => 'course',
            'object_id' => 91,
            'payload' => ['grant_id' => 8],
            'occurred_at' => '2026-08-10 10:00:00',
        ];
    }
}

final class MemoryActivityEvents implements ActivityEventStore
{
    /** @param list<array<string, mixed>> $events */
    public function __construct(private array $events)
    {
    }

    public function record(DomainEvent $event): array|WP_Error
    {
        $this->events[] = $event->toRecord();

        return ['id' => count($this->events), 'created' => true];
    }

    public function find(ActivityEventQuery $query): array|WP_Error
    {
        return array_slice($this->events, 0, $query->limit());
    }
}
