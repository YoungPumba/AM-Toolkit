<?php

namespace AMToolkit\Modules\Courses;

use AMToolkit\Modules\Courses\Contracts\ProgressRepository;
use AMToolkit\Modules\Courses\Domain\LessonProgress;

defined('ABSPATH') || exit;

final class WpdbProgressRepository implements ProgressRepository
{
    private \wpdb $database;

    public function __construct(?\wpdb $database = null)
    {
        global $wpdb;

        $this->database = $database ?? $wpdb;
    }

    public function find(int $userId, int $courseId, int $lessonId): ?LessonProgress
    {
        $row = $this->database->get_row(
            $this->database->prepare(
                'SELECT id, user_id, course_id, lesson_id, status, content_version,'
                . ' completion_source, completed_at, request_id FROM ' . CoursesSchema::progressTable()
                . ' WHERE user_id = %d AND course_id = %d AND lesson_id = %d LIMIT 1',
                $userId,
                $courseId,
                $lessonId
            ),
            ARRAY_A
        );

        if (!is_array($row)) {
            return null;
        }

        return new LessonProgress(
            (int) $row['id'],
            (int) $row['user_id'],
            (int) $row['course_id'],
            (int) $row['lesson_id'],
            (string) $row['status'],
            (int) $row['content_version'],
            isset($row['completion_source']) ? (string) $row['completion_source'] : null,
            isset($row['completed_at']) ? (string) $row['completed_at'] : null,
            isset($row['request_id']) ? (string) $row['request_id'] : null
        );
    }

    public function save(LessonProgress $progress): bool
    {
        $now = current_time('mysql', true);
        $sql = $this->database->prepare(
            'INSERT INTO ' . CoursesSchema::progressTable() . ' ('
            . 'user_id, course_id, lesson_id, status, completion_source, request_id,'
            . ' content_version, completed_at, created_at, updated_at'
            . ') VALUES (%d, %d, %d, %s, NULLIF(%s, \'\'), NULLIF(%s, \'\'), %d, NULLIF(%s, \'\'), %s, %s)'
            . ' ON DUPLICATE KEY UPDATE '
            . 'status = IF(content_version = VALUES(content_version) AND status = \'completed\''
            . ' AND VALUES(status) = \'started\', status, VALUES(status)), '
            . 'completion_source = IF(content_version = VALUES(content_version) AND status = \'completed\''
            . ' AND VALUES(status) = \'started\', completion_source, VALUES(completion_source)), '
            . 'request_id = IF(content_version = VALUES(content_version) AND status = \'completed\''
            . ' AND VALUES(status) = \'started\', request_id, VALUES(request_id)), '
            . 'completed_at = IF(content_version = VALUES(content_version) AND status = \'completed\''
            . ' AND VALUES(status) = \'started\', completed_at, VALUES(completed_at)), '
            . 'content_version = VALUES(content_version), updated_at = VALUES(updated_at)',
            $progress->userId(),
            $progress->courseId(),
            $progress->lessonId(),
            $progress->status(),
            (string) $progress->completionSource(),
            (string) $progress->requestId(),
            $progress->contentVersion(),
            (string) $progress->completedAt(),
            $now,
            $now
        );

        return is_string($sql) && $this->database->query($sql) !== false;
    }

    public function completedLessonIds(int $userId, int $courseId, array $lessonIds): array
    {
        $lessonIds = array_values(array_unique(array_filter(array_map('absint', $lessonIds))));

        if ($lessonIds === []) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, count($lessonIds), '%d'));
        $progress = CoursesSchema::progressTable();
        $lessons = CoursesSchema::lessonsTable();
        $sql = $this->database->prepare(
            "SELECT p.lesson_id FROM {$progress} p"
            . " INNER JOIN {$lessons} l ON l.id = p.lesson_id"
            . " AND l.course_id = p.course_id AND l.content_version = p.content_version"
            . " WHERE p.user_id = %d AND p.course_id = %d AND p.status = 'completed'"
            . " AND p.lesson_id IN ({$placeholders})",
            $userId,
            $courseId,
            ...$lessonIds
        );
        $values = is_string($sql) ? $this->database->get_col($sql) : [];

        return array_values(array_unique(array_map('intval', $values)));
    }
}
