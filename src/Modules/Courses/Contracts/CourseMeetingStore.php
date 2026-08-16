<?php

namespace AMToolkit\Modules\Courses\Contracts;

defined('ABSPATH') || exit;

interface CourseMeetingStore
{
    /** @return array<string, mixed>|null|\WP_Error */
    public function courseSettings(int $courseId): array|null|\WP_Error;

    /** @return list<array<string, mixed>>|\WP_Error */
    public function meetingsForCourse(int $courseId): array|\WP_Error;

    /** @param array<string, mixed> $meeting */
    public function saveMeeting(array $meeting, int $actorId, string $requestId): int|\WP_Error;

    public function saveTelegramReference(int $courseId, ?string $reference): bool|\WP_Error;

    /**
     * @param list<int> $courseIds
     * @return array<int, array<string, mixed>>|\WP_Error
     */
    public function nearestMeetings(array $courseIds, string $atUtc): array|\WP_Error;
}
