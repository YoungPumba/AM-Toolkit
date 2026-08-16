<?php

declare(strict_types=1);

namespace AMToolkit\Tests\Unit;

use AMToolkit\Core\Diagnostics\ActivityEventQuery;
use AMToolkit\Core\Diagnostics\DomainEvent;
use AMToolkit\Modules\Access\ActivityEventStore;
use AMToolkit\Modules\Courses\Contracts\CourseMeetingStore;
use AMToolkit\Modules\Courses\Services\CourseMeetingService;
use PHPUnit\Framework\TestCase;

final class CourseMeetingServiceTest extends TestCase
{
    public function testConvertsWarsawSummerAndWinterTimesToUtcWithoutLoggingPrivateLinks(): void
    {
        $store = new MeetingMemoryStore();
        $events = new MeetingEventStore();
        $service = new CourseMeetingService($store, $events);

        $summer = $service->saveMeeting($this->meetingInput('2026-08-20T20:00', '2026-08-20T21:00'), 12);
        self::assertSame(1, $summer);
        self::assertSame('2026-08-20 18:00:00', $store->saved['starts_at_utc']);
        self::assertSame('https://zoom.example/join/secret', $store->saved['join_reference']);

        $winter = $service->saveMeeting($this->meetingInput('2027-01-20T20:00', '2027-01-20T21:00'), 12);
        self::assertSame(1, $winter);
        self::assertSame('2027-01-20 19:00:00', $store->saved['starts_at_utc']);

        $payload = $events->events[1]->toRecord()['payload'];
        self::assertTrue($payload['has_join_link']);
        self::assertArrayNotHasKey('join_reference', $payload);
        self::assertStringNotContainsString('secret', json_encode($payload, JSON_THROW_ON_ERROR));
    }

    public function testRejectsNonHttpsPrivateLink(): void
    {
        $service = new CourseMeetingService(new MeetingMemoryStore(), new MeetingEventStore());
        $input = $this->meetingInput('2026-08-20T20:00', '2026-08-20T21:00');
        $input['join_reference'] = 'http://example.test/private';

        $result = $service->saveMeeting($input, 12);

        self::assertTrue(is_wp_error($result));
        self::assertSame('am_toolkit_course_private_url_invalid', $result->get_error_code());
    }

    public function testChangingScheduledStartMarksMeetingAsRescheduled(): void
    {
        $store = new MeetingMemoryStore();
        $store->meetings = [[
            'id' => 9,
            'starts_at_utc' => '2026-08-20 18:00:00',
        ]];
        $service = new CourseMeetingService($store, new MeetingEventStore());
        $input = $this->meetingInput('2026-08-21T20:00', '2026-08-21T21:00');
        $input['id'] = 9;

        self::assertSame(9, $service->saveMeeting($input, 12));
        self::assertSame('rescheduled', $store->saved['status']);
    }

    /** @return array<string, mixed> */
    private function meetingInput(string $start, string $end): array
    {
        return [
            'id' => 0,
            'course_id' => 5,
            'title' => 'Q&A na żywo',
            'description' => 'Pytania do materiału',
            'starts_at' => $start,
            'ends_at' => $end,
            'display_timezone' => 'Europe/Warsaw',
            'platform' => 'Zoom',
            'location' => 'Online',
            'join_reference' => 'https://zoom.example/join/secret',
            'recording_reference' => '',
            'status' => 'scheduled',
        ];
    }
}

final class MeetingMemoryStore implements CourseMeetingStore
{
    /** @var array<string, mixed> */
    public array $saved = [];
    /** @var list<array<string, mixed>> */
    public array $meetings = [];

    public function courseSettings(int $courseId): array|null|\WP_Error
    {
        return ['id' => $courseId, 'telegram_reference' => null];
    }

    public function meetingsForCourse(int $courseId): array|\WP_Error
    {
        return $this->meetings;
    }

    public function saveMeeting(array $meeting, int $actorId, string $requestId): int|\WP_Error
    {
        $this->saved = $meeting;
        return (int) ($meeting['id'] ?: 1);
    }

    public function saveTelegramReference(int $courseId, ?string $reference): bool|\WP_Error
    {
        return true;
    }

    public function nearestMeetings(array $courseIds, string $atUtc): array|\WP_Error
    {
        return [];
    }
}

final class MeetingEventStore implements ActivityEventStore
{
    /** @var list<DomainEvent> */
    public array $events = [];

    public function record(DomainEvent $event): array|\WP_Error
    {
        $this->events[] = $event;
        return ['id' => count($this->events), 'created' => true];
    }

    public function find(ActivityEventQuery $query): array|\WP_Error
    {
        return [];
    }
}
