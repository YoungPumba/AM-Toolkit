<?php

namespace AMToolkit\Modules\Courses;

use AMToolkit\Modules\Courses\Contracts\CompletionRepository;
use AMToolkit\Modules\Courses\Domain\CourseCompletion;

defined('ABSPATH') || exit;

final class WpdbCompletionRepository implements CompletionRepository
{
    private \wpdb $database;

    public function __construct(?\wpdb $database = null)
    {
        global $wpdb;

        $this->database = $database ?? $wpdb;
    }

    public function find(int $userId, int $courseId, int $programVersionId): ?CourseCompletion
    {
        $row = $this->database->get_row(
            $this->database->prepare(
                'SELECT id, user_id, course_id, program_version_id, required_lesson_ids,'
                . ' completion_source, completed_at, request_id FROM ' . CoursesSchema::completionsTable()
                . ' WHERE user_id = %d AND course_id = %d AND program_version_id = %d LIMIT 1',
                $userId,
                $courseId,
                $programVersionId
            ),
            ARRAY_A
        );

        if (!is_array($row)) {
            return null;
        }

        $lessonIds = json_decode((string) $row['required_lesson_ids'], true);

        return new CourseCompletion(
            (int) $row['id'],
            (int) $row['user_id'],
            (int) $row['course_id'],
            (int) $row['program_version_id'],
            array_values(array_map('intval', is_array($lessonIds) ? $lessonIds : [])),
            (string) $row['completion_source'],
            (string) $row['completed_at'],
            isset($row['request_id']) ? (string) $row['request_id'] : null
        );
    }

    public function record(CourseCompletion $completion): bool
    {
        $lessonIds = wp_json_encode($completion->requiredLessonIds());

        if ($lessonIds === false) {
            return false;
        }

        $sql = $this->database->prepare(
            'INSERT INTO ' . CoursesSchema::completionsTable() . ' ('
            . 'user_id, course_id, program_version_id, required_lesson_ids, requirements_hash,'
            . ' completion_source, request_id, completed_at, created_at'
            . ') VALUES (%d, %d, %d, %s, %s, %s, NULLIF(%s, \'\'), %s, %s)'
            . ' ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)',
            $completion->userId(),
            $completion->courseId(),
            $completion->programVersionId(),
            $lessonIds,
            $completion->requirementsHash(),
            $completion->completionSource(),
            (string) $completion->requestId(),
            $completion->completedAt(),
            $completion->completedAt()
        );

        return is_string($sql) && $this->database->query($sql) !== false;
    }
}
