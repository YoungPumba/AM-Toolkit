<?php

namespace AMToolkit\Modules\Courses\Frontend;

use AMToolkit\Modules\Courses\Services\CourseCatalogService;

defined('ABSPATH') || exit;

final class CourseAttentionTasks
{
    public function __construct(private CourseCatalogService $courses)
    {
    }

    public function boot(): void
    {
        add_filter('am_toolkit_account_attention_tasks', [$this, 'append'], 10, 2);
    }

    /**
     * @param list<array<string, mixed>> $tasks
     * @return list<array<string, mixed>>
     */
    public function append(array $tasks, object $user): array
    {
        $userId = isset($user->ID) ? (int) $user->ID : 0;
        if ($userId <= 0 || !function_exists('wc_get_endpoint_url')) {
            return $tasks;
        }

        $courses = $this->courses->coursesForUser($userId);
        if (is_wp_error($courses)) {
            return $tasks;
        }

        $meetings = [];
        foreach ($courses as $course) {
            if (empty($course['can_open']) || !isset($course['nearest_meeting']) || !is_array($course['nearest_meeting'])) {
                continue;
            }

            $meeting = $course['nearest_meeting'];
            $date = $this->meetingDate($meeting);
            $publicId = (string) ($course['public_id'] ?? '');
            if ($date === '' || $publicId === '') {
                continue;
            }

            $meetings[] = [
                'label' => trim((string) ($meeting['title'] ?? '')) ?: __('Najbliższe spotkanie kursowe', 'am-toolkit'),
                'meta' => sprintf(
                    /* translators: 1: course title, 2: meeting date. */
                    __('%1$s · %2$s', 'am-toolkit'),
                    (string) ($course['title'] ?? __('Kurs', 'am-toolkit')),
                    $date
                ),
                'url' => $this->courseUrl($publicId),
                'type' => 'course-meeting',
                'icon' => 'calendar',
                'starts_at_utc' => (string) ($meeting['starts_at_utc'] ?? ''),
            ];
        }

        usort(
            $meetings,
            static fn (array $left, array $right): int => strcmp(
                $left['starts_at_utc'],
                $right['starts_at_utc']
            )
        );

        foreach ($meetings as $meeting) {
            unset($meeting['starts_at_utc']);
            $tasks[] = $meeting;
        }

        return $tasks;
    }

    /** @param array<string, mixed> $meeting */
    private function meetingDate(array $meeting): string
    {
        try {
            $timezone = new \DateTimeZone((string) ($meeting['display_timezone'] ?? 'Europe/Warsaw'));
            $date = new \DateTimeImmutable((string) ($meeting['starts_at_utc'] ?? ''), new \DateTimeZone('UTC'));

            return wp_date('j.m.Y · H:i', $date->getTimestamp(), $timezone);
        } catch (\Throwable) {
            return '';
        }
    }

    private function courseUrl(string $publicId): string
    {
        return wc_get_endpoint_url('kursy', rawurlencode($publicId), wc_get_page_permalink('myaccount'));
    }
}
