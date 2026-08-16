<?php

namespace AMToolkit\Modules\Courses\Services;

use AMToolkit\Modules\Courses\Contracts\CourseProgressOverviewStore;
use AMToolkit\Modules\Courses\Domain\ProgressStatus;

defined('ABSPATH') || exit;

final class CourseNextActionService
{
    public function __construct(private CourseProgressOverviewStore $store)
    {
    }

    /** @return array<string, mixed>|\WP_Error */
    public function overview(int $userId, int $courseId, int $programVersionId): array|\WP_Error
    {
        $rows = $this->store->lessons($userId, $courseId, $programVersionId);

        if (is_wp_error($rows)) {
            return $rows;
        }

        $hasCompletion = $this->store->hasCompletion($userId, $courseId, $programVersionId);

        if (is_wp_error($hasCompletion)) {
            return $hasCompletion;
        }

        $requiredTotal = 0;
        $requiredCompleted = 0;
        $statuses = [];
        $started = [];
        $firstRequired = null;
        $firstOptional = null;

        foreach ($rows as $row) {
            $publicId = (string) ($row['public_id'] ?? '');

            if ($publicId === '') {
                continue;
            }

            $status = (string) ($row['progress_status'] ?? '');
            $status = in_array($status, [ProgressStatus::STARTED, ProgressStatus::COMPLETED], true)
                ? $status
                : 'no_record';
            $required = !empty($row['is_required']);
            $statuses[$publicId] = $status;

            if ($required) {
                $requiredTotal++;

                if ($status === ProgressStatus::COMPLETED) {
                    $requiredCompleted++;
                } elseif ($firstRequired === null) {
                    $firstRequired = $publicId;
                }
            } elseif ($status !== ProgressStatus::COMPLETED && $firstOptional === null) {
                $firstOptional = $publicId;
            }

            if ($status === ProgressStatus::STARTED) {
                $started[] = [
                    'public_id' => $publicId,
                    'updated_at' => (string) ($row['progress_updated_at'] ?? ''),
                ];
            }
        }

        usort($started, static fn (array $left, array $right): int => $right['updated_at'] <=> $left['updated_at']);
        $nextLesson = $hasCompletion
            ? ''
            : (string) ($started[0]['public_id'] ?? $firstRequired ?? $firstOptional ?? '');

        return [
            'course_completed' => $hasCompletion,
            'progress_percent' => $hasCompletion || $requiredTotal === 0
                ? 100
                : (int) floor(($requiredCompleted / $requiredTotal) * 100),
            'required_total' => $requiredTotal,
            'required_completed' => $requiredCompleted,
            'lesson_statuses' => $statuses,
            'next_lesson_public_id' => $nextLesson !== '' ? $nextLesson : null,
            'next_action' => $nextLesson !== ''
                ? ($started !== [] ? 'continue' : 'start')
                : ($hasCompletion ? 'completed' : 'program'),
        ];
    }
}
