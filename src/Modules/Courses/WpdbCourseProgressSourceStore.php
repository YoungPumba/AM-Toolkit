<?php

namespace AMToolkit\Modules\Courses;

use AMToolkit\Modules\Courses\Contracts\CourseProgressSourceStore;
use AMToolkit\Modules\Courses\Domain\Identifier;

defined('ABSPATH') || exit;

final class WpdbCourseProgressSourceStore implements CourseProgressSourceStore
{
    private \wpdb $database;

    public function __construct(?\wpdb $database = null)
    {
        global $wpdb;

        $this->database = $database ?? $wpdb;
    }

    public function lessonContext(Identifier $coursePublicId, Identifier $lessonPublicId): array|null|\WP_Error
    {
        $courses = CoursesSchema::coursesTable();
        $programs = CoursesSchema::programVersionsTable();
        $lessons = CoursesSchema::lessonsTable();
        $assignments = CoursesSchema::programLessonsTable();
        $row = $this->database->get_row(
            $this->database->prepare(
                "SELECT c.id AS course_id, c.current_program_version_id AS program_version_id,
                    l.id AS lesson_id, l.content_version, l.duration_seconds,
                    l.completion_requirements
                FROM {$courses} c
                INNER JOIN {$programs} p
                    ON p.id = c.current_program_version_id
                    AND p.course_id = c.id AND p.status = 'published'
                INNER JOIN {$assignments} pl ON pl.program_version_id = p.id
                INNER JOIN {$lessons} l
                    ON l.id = pl.lesson_id AND l.course_id = c.id AND l.status = 'published'
                WHERE c.public_id = %s AND c.status = 'published' AND l.public_id = %s
                LIMIT 1",
                $coursePublicId->value(),
                $lessonPublicId->value()
            ),
            ARRAY_A
        );

        if ($this->database->last_error !== '') {
            return $this->databaseError();
        }

        if (!is_array($row)) {
            return null;
        }

        $programRows = $this->database->get_results(
            $this->database->prepare(
                'SELECT lesson_id, is_required FROM ' . $assignments
                . ' WHERE program_version_id = %d ORDER BY position ASC, id ASC',
                (int) $row['program_version_id']
            ),
            ARRAY_A
        );
        if (!is_array($programRows)) {
            return $this->databaseError();
        }

        $requirements = json_decode((string) ($row['completion_requirements'] ?? ''), true);
        $row['completion_requirements'] = is_array($requirements) ? $requirements : [];
        $row['lesson_ids'] = [];
        $row['required_lesson_ids'] = [];

        foreach ($programRows as $programRow) {
            $lessonId = (int) ($programRow['lesson_id'] ?? 0);

            if ($lessonId <= 0) {
                continue;
            }

            $row['lesson_ids'][] = $lessonId;

            if (!empty($programRow['is_required'])) {
                $row['required_lesson_ids'][] = $lessonId;
            }
        }

        return $row;
    }

    public function recordVideoCheckpoint(
        int $userId,
        int $courseId,
        int $lessonId,
        int $contentVersion,
        string $requestId,
        array $intervals,
        int $durationSeconds,
        float $coveredSeconds,
        string $occurredAt
    ): bool|\WP_Error {
        $encoded = wp_json_encode($intervals);

        if ($encoded === false) {
            return $this->writeError('am_toolkit_course_checkpoint_encode_failed');
        }

        $sql = $this->database->prepare(
            'INSERT INTO ' . CoursesSchema::videoCheckpointsTable()
            . ' (user_id, course_id, lesson_id, content_version, request_id, intervals,'
            . ' duration_seconds, covered_seconds, occurred_at)'
            . ' VALUES (%d, %d, %d, %d, %s, %s, %d, %f, %s)'
            . ' ON DUPLICATE KEY UPDATE id = id',
            $userId,
            $courseId,
            $lessonId,
            $contentVersion,
            $requestId,
            $encoded,
            $durationSeconds,
            $coveredSeconds,
            $occurredAt
        );
        $result = is_string($sql) ? $this->database->query($sql) : false;

        if ($result === false) {
            return $this->writeError('am_toolkit_course_checkpoint_write_failed');
        }

        return $result === 1;
    }

    public function videoCheckpointIntervals(int $userId, int $lessonId, int $contentVersion): array|\WP_Error
    {
        $rows = $this->database->get_col(
            $this->database->prepare(
                'SELECT intervals FROM ' . CoursesSchema::videoCheckpointsTable()
                . ' WHERE user_id = %d AND lesson_id = %d AND content_version = %d'
                . ' ORDER BY id ASC',
                $userId,
                $lessonId,
                $contentVersion
            )
        );

        if ($this->database->last_error !== '') {
            return $this->databaseError();
        }

        $sets = [];

        foreach ($rows as $encoded) {
            $decoded = json_decode((string) $encoded, true);

            if (!is_array($decoded)) {
                continue;
            }

            $intervals = [];

            foreach ($decoded as $interval) {
                if (is_array($interval) && count($interval) === 2) {
                    $intervals[] = [(float) $interval[0], (float) $interval[1]];
                }
            }

            $sets[] = $intervals;
        }

        return $sets;
    }

    public function latestVideoPosition(int $userId, int $lessonId, int $contentVersion): float|\WP_Error
    {
        $encoded = $this->database->get_var(
            $this->database->prepare(
                'SELECT intervals FROM ' . CoursesSchema::videoCheckpointsTable()
                . ' WHERE user_id = %d AND lesson_id = %d AND content_version = %d'
                . ' ORDER BY id DESC LIMIT 1',
                $userId,
                $lessonId,
                $contentVersion
            )
        );

        if ($this->database->last_error !== '') {
            return $this->databaseError();
        }

        $decoded = json_decode((string) $encoded, true);
        $position = 0.0;

        if (is_array($decoded)) {
            foreach ($decoded as $interval) {
                if (is_array($interval) && count($interval) === 2 && is_numeric($interval[1] ?? null)) {
                    $position = max($position, (float) $interval[1]);
                }
            }
        }

        return $position;
    }

    public function recordRequirementCompletion(
        int $userId,
        int $courseId,
        int $lessonId,
        int $contentVersion,
        string $requirementKey,
        string $completionSource,
        string $requestId,
        string $completedAt
    ): bool|\WP_Error {
        $sql = $this->database->prepare(
            'INSERT INTO ' . CoursesSchema::requirementCompletionsTable()
            . ' (user_id, course_id, lesson_id, content_version, requirement_key,'
            . ' completion_source, request_id, completed_at)'
            . ' VALUES (%d, %d, %d, %d, %s, %s, %s, %s)'
            . ' ON DUPLICATE KEY UPDATE id = id',
            $userId,
            $courseId,
            $lessonId,
            $contentVersion,
            $requirementKey,
            $completionSource,
            $requestId,
            $completedAt
        );
        $result = is_string($sql) ? $this->database->query($sql) : false;

        if ($result === false) {
            return $this->writeError('am_toolkit_course_requirement_write_failed');
        }

        return $result === 1;
    }

    public function hasRequirementCompletion(
        int $userId,
        int $lessonId,
        int $contentVersion,
        string $requirementKey
    ): bool|\WP_Error {
        $id = $this->database->get_var(
            $this->database->prepare(
                'SELECT id FROM ' . CoursesSchema::requirementCompletionsTable()
                . ' WHERE user_id = %d AND lesson_id = %d AND content_version = %d'
                . ' AND requirement_key = %s LIMIT 1',
                $userId,
                $lessonId,
                $contentVersion,
                $requirementKey
            )
        );

        if ($this->database->last_error !== '') {
            return $this->databaseError();
        }

        return (int) $id > 0;
    }

    private function databaseError(): \WP_Error
    {
        return new \WP_Error(
            'am_toolkit_course_progress_database_failed',
            __('Nie udało się odczytać postępu kursu.', 'am-toolkit'),
            ['database_error' => $this->database->last_error]
        );
    }

    private function writeError(string $code): \WP_Error
    {
        return new \WP_Error(
            $code,
            __('Nie udało się zapisać postępu kursu.', 'am-toolkit'),
            ['database_error' => $this->database->last_error]
        );
    }
}
