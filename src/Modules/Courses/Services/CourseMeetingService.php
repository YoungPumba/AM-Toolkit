<?php

namespace AMToolkit\Modules\Courses\Services;

use AMToolkit\Core\Diagnostics\DomainEvent;
use AMToolkit\Core\Diagnostics\RequestId;
use AMToolkit\Modules\Access\ActivityEventStore;
use AMToolkit\Modules\Courses\Contracts\CourseMeetingStore;
use AMToolkit\Modules\Courses\Domain\MeetingStatus;

defined('ABSPATH') || exit;

final class CourseMeetingService
{
    public function __construct(
        private CourseMeetingStore $store,
        private ActivityEventStore $events
    ) {
    }

    public function courseSettings(int $courseId): array|null|\WP_Error
    {
        return $courseId > 0 ? $this->store->courseSettings($courseId) : $this->invalid();
    }

    public function meetings(int $courseId): array|\WP_Error
    {
        return $courseId > 0 ? $this->store->meetingsForCourse($courseId) : $this->invalid();
    }

    public function saveTelegram(int $courseId, string $reference, int $actorId, ?string $requestId = null): bool|\WP_Error
    {
        if ($courseId <= 0) {
            return $this->invalid();
        }

        $url = $this->privateUrl($reference);
        if (is_wp_error($url)) {
            return $url;
        }

        $requestId = RequestId::normalize($requestId);
        $saved = $this->store->saveTelegramReference($courseId, $url);
        if (is_wp_error($saved)) {
            return $saved;
        }

        $recorded = $this->record('course.telegram.updated', $courseId, $actorId, [
            'has_private_link' => $url !== null,
        ], $requestId);

        return is_wp_error($recorded) ? $recorded : true;
    }

    /** @param array<string, mixed> $input */
    public function saveMeeting(array $input, int $actorId, ?string $requestId = null): int|\WP_Error
    {
        $meetingId = max(0, (int) ($input['id'] ?? 0));
        $courseId = max(0, (int) ($input['course_id'] ?? 0));
        $title = trim((string) ($input['title'] ?? ''));
        $timezone = trim((string) ($input['display_timezone'] ?? 'Europe/Warsaw'));
        $status = sanitize_key((string) ($input['status'] ?? MeetingStatus::SCHEDULED));

        if ($courseId <= 0 || $title === '') {
            return $this->invalid();
        }

        try {
            MeetingStatus::assertValid($status);
            $zone = new \DateTimeZone($timezone);
        } catch (\Throwable) {
            return $this->invalid();
        }

        $startsAt = $this->localDate((string) ($input['starts_at'] ?? ''), $zone);
        $endsAt = $this->localDate((string) ($input['ends_at'] ?? ''), $zone);
        if ($startsAt === null || $endsAt === null || $endsAt <= $startsAt) {
            return new \WP_Error('am_toolkit_course_meeting_time_invalid', __('Podaj poprawny początek i koniec spotkania.', 'am-toolkit'));
        }

        $join = $this->privateUrl((string) ($input['join_reference'] ?? ''));
        $recording = $this->privateUrl((string) ($input['recording_reference'] ?? ''));
        if (is_wp_error($join)) {
            return $join;
        }
        if (is_wp_error($recording)) {
            return $recording;
        }

        if ($meetingId > 0 && $status === MeetingStatus::SCHEDULED) {
            $existing = $this->store->meetingsForCourse($courseId);
            if (is_wp_error($existing)) {
                return $existing;
            }
            foreach ($existing as $meeting) {
                if ((int) ($meeting['id'] ?? 0) === $meetingId
                    && (string) ($meeting['starts_at_utc'] ?? '') !== $startsAt->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s')) {
                    $status = MeetingStatus::RESCHEDULED;
                    break;
                }
            }
        }

        $requestId = RequestId::normalize($requestId);
        $utc = new \DateTimeZone('UTC');
        $meeting = [
            'id' => $meetingId,
            'course_id' => $courseId,
            'title' => $title,
            'description' => trim((string) ($input['description'] ?? '')),
            'starts_at_utc' => $startsAt->setTimezone($utc)->format('Y-m-d H:i:s'),
            'ends_at_utc' => $endsAt->setTimezone($utc)->format('Y-m-d H:i:s'),
            'display_timezone' => $timezone,
            'platform' => trim((string) ($input['platform'] ?? '')),
            'location' => trim((string) ($input['location'] ?? '')),
            'join_reference' => $join,
            'recording_reference' => $recording,
            'status' => $status,
        ];
        $savedId = $this->store->saveMeeting($meeting, $actorId, $requestId);
        if (is_wp_error($savedId)) {
            return $savedId;
        }

        $recorded = $this->record('meeting.updated', $courseId, $actorId, [
            'meeting_id' => $savedId,
            'status' => $status,
            'starts_at_utc' => $meeting['starts_at_utc'],
            'ends_at_utc' => $meeting['ends_at_utc'],
            'has_join_link' => $join !== null,
            'has_recording_link' => $recording !== null,
        ], $requestId);

        return is_wp_error($recorded) ? $recorded : $savedId;
    }

    private function localDate(string $value, \DateTimeZone $timezone): ?\DateTimeImmutable
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', trim($value), $timezone);
        $errors = \DateTimeImmutable::getLastErrors();
        if ($date === false || (is_array($errors) && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
            return null;
        }

        return $date->format('Y-m-d\TH:i') === trim($value) ? $date : null;
    }

    private function privateUrl(string $value): string|null|\WP_Error
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        if (filter_var($value, FILTER_VALIDATE_URL) === false || strtolower((string) parse_url($value, PHP_URL_SCHEME)) !== 'https') {
            return new \WP_Error('am_toolkit_course_private_url_invalid', __('Prywatny odnośnik musi być poprawnym adresem HTTPS.', 'am-toolkit'));
        }

        return $value;
    }

    /** @param array<string, mixed> $payload */
    private function record(string $type, int $courseId, int $actorId, array $payload, string $requestId): bool|\WP_Error
    {
        $result = $this->events->record(DomainEvent::create(
            $type . ':' . $courseId . ':' . $requestId,
            $type,
            0,
            max(0, $actorId),
            'course',
            $courseId,
            $payload,
            current_time('mysql', true),
            $requestId
        ));

        return is_wp_error($result) ? $result : true;
    }

    private function invalid(): \WP_Error
    {
        return new \WP_Error('am_toolkit_course_meeting_invalid', __('Dane spotkania są nieprawidłowe.', 'am-toolkit'));
    }
}
