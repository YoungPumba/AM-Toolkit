<?php

namespace AMToolkit\Modules\Courses;

use AMToolkit\Modules\Courses\Contracts\CourseLessonTaskStore;
use AMToolkit\Modules\Courses\Domain\Identifier;

defined('ABSPATH') || exit;

final class WpdbCourseLessonTaskStore implements CourseLessonTaskStore
{
    private \wpdb $database;

    public function __construct(?\wpdb $database = null)
    {
        global $wpdb;
        $this->database = $database ?? $wpdb;
    }

    public function tasksForCourse(int $courseId): array|\WP_Error
    {
        $tasks = CoursesSchema::lessonTasksTable();
        $lessons = CoursesSchema::lessonsTable();
        $rows = $this->database->get_results(
            $this->database->prepare(
                "SELECT t.id, t.public_id, t.lesson_id, t.title, t.description, t.position,
                    t.is_required, t.status, t.updated_at, l.title AS lesson_title
                FROM {$tasks} t
                INNER JOIN {$lessons} l ON l.id = t.lesson_id
                WHERE l.course_id = %d
                ORDER BY l.id ASC, t.position ASC, t.id ASC",
                $courseId
            ),
            ARRAY_A
        );

        return $this->rowsOrError($rows);
    }

    public function publishedTasksForLesson(int $lessonId): array|\WP_Error
    {
        $rows = $this->database->get_results(
            $this->database->prepare(
                'SELECT id, public_id, title, description, position, is_required'
                . ' FROM ' . CoursesSchema::lessonTasksTable()
                . " WHERE lesson_id = %d AND status = 'published' AND archived_at IS NULL"
                . ' ORDER BY position ASC, id ASC',
                $lessonId
            ),
            ARRAY_A
        );

        return $this->rowsOrError($rows);
    }

    public function saveTask(array $task): int|\WP_Error
    {
        $taskId = max(0, (int) ($task['id'] ?? 0));
        $courseId = (int) ($task['course_id'] ?? 0);
        $lessonId = (int) ($task['lesson_id'] ?? 0);
        $belongs = $this->findId($this->database->prepare(
            'SELECT id FROM ' . CoursesSchema::lessonsTable()
            . ' WHERE id = %d AND course_id = %d LIMIT 1',
            $lessonId,
            $courseId
        ));

        if (is_wp_error($belongs)) {
            return $belongs;
        }

        if ($belongs === null) {
            return $this->notFound(__('Wybrana lekcja nie należy do tego kursu.', 'am-toolkit'));
        }

        $table = CoursesSchema::lessonTasksTable();

        if ($taskId > 0) {
            $existing = $this->findId($this->database->prepare(
                "SELECT t.id FROM {$table} t"
                . ' INNER JOIN ' . CoursesSchema::lessonsTable() . ' l ON l.id = t.lesson_id'
                . ' WHERE t.id = %d AND l.course_id = %d LIMIT 1',
                $taskId,
                $courseId
            ));

            if (is_wp_error($existing)) {
                return $existing;
            }

            if ($existing === null) {
                return $this->notFound(__('Nie znaleziono tego zadania w kursie.', 'am-toolkit'));
            }

            $existingLessonId = $this->database->get_var($this->database->prepare(
                "SELECT t.lesson_id FROM {$table} t"
                . ' INNER JOIN ' . CoursesSchema::lessonsTable() . ' l ON l.id = t.lesson_id'
                . ' WHERE t.id = %d AND l.course_id = %d LIMIT 1',
                $taskId,
                $courseId
            ));

            if ($this->database->last_error !== '') {
                return $this->databaseError();
            }

            if ((int) $existingLessonId !== $lessonId) {
                return new \WP_Error(
                    'am_toolkit_lesson_task_move_forbidden',
                    __('Nie można przenieść istniejącego zadania do innej lekcji. Utwórz nową pozycję.', 'am-toolkit')
                );
            }
        }

        $now = current_time('mysql', true);
        $status = (string) ($task['status'] ?? 'draft');
        $data = [
            'lesson_id' => $lessonId,
            'title' => (string) ($task['title'] ?? ''),
            'description' => (string) ($task['description'] ?? ''),
            'position' => max(0, (int) ($task['position'] ?? 0)),
            'is_required' => !empty($task['is_required']) ? 1 : 0,
            'status' => $status,
            'updated_at' => $now,
            'archived_at' => $status === 'archived' ? $now : null,
        ];

        if ($taskId > 0) {
            $saved = $this->database->update($table, $data, ['id' => $taskId]);

            return $saved === false ? $this->databaseError() : $taskId;
        }

        $data += [
            'public_id' => wp_generate_uuid4(),
            'created_at' => $now,
        ];

        if ($this->database->insert($table, $data) !== 1) {
            return $this->databaseError();
        }

        return (int) $this->database->insert_id;
    }

    public function archiveTask(int $taskId, int $courseId): bool|\WP_Error
    {
        $table = CoursesSchema::lessonTasksTable();
        $lessons = CoursesSchema::lessonsTable();
        $belongs = $this->findId($this->database->prepare(
            "SELECT t.id FROM {$table} t INNER JOIN {$lessons} l ON l.id = t.lesson_id"
            . ' WHERE t.id = %d AND l.course_id = %d LIMIT 1',
            $taskId,
            $courseId
        ));

        if (is_wp_error($belongs)) {
            return $belongs;
        }

        if ($belongs === null) {
            return $this->notFound(__('Nie znaleziono tego zadania w kursie.', 'am-toolkit'));
        }

        $now = current_time('mysql', true);
        $saved = $this->database->update(
            $table,
            ['status' => 'archived', 'archived_at' => $now, 'updated_at' => $now],
            ['id' => $taskId]
        );

        return $saved === false ? $this->databaseError() : true;
    }

    public function completedTaskIds(int $userId, int $lessonId): array|\WP_Error
    {
        $values = $this->database->get_col(
            $this->database->prepare(
                'SELECT task_id FROM ' . CoursesSchema::lessonTaskProgressTable()
                . ' WHERE user_id = %d AND lesson_id = %d AND is_completed = 1',
                $userId,
                $lessonId
            )
        );

        if ($this->database->last_error !== '') {
            return $this->databaseError();
        }

        return array_values(array_unique(array_map('intval', $values)));
    }

    public function setTaskCompletion(
        int $userId,
        int $courseId,
        int $lessonId,
        Identifier $taskPublicId,
        bool $completed,
        string $requestId,
        string $occurredAt
    ): int|\WP_Error {
        $tasks = CoursesSchema::lessonTasksTable();
        $lessons = CoursesSchema::lessonsTable();
        $taskId = $this->findId($this->database->prepare(
            "SELECT t.id FROM {$tasks} t INNER JOIN {$lessons} l ON l.id = t.lesson_id"
            . " WHERE t.public_id = %s AND t.lesson_id = %d AND l.course_id = %d"
            . " AND t.status = 'published' AND t.archived_at IS NULL LIMIT 1",
            $taskPublicId->value(),
            $lessonId,
            $courseId
        ));

        if (is_wp_error($taskId)) {
            return $taskId;
        }

        if ($taskId === null) {
            return $this->notFound(__('To zadanie nie jest dostępne w tej lekcji.', 'am-toolkit'));
        }

        $sql = $this->database->prepare(
            'INSERT INTO ' . CoursesSchema::lessonTaskProgressTable()
            . ' (user_id, course_id, lesson_id, task_id, is_completed, request_id,'
            . ' completed_at, created_at, updated_at)'
            . ' VALUES (%d, %d, %d, %d, %d, %s, NULLIF(%s, \'\'), %s, %s)'
            . ' ON DUPLICATE KEY UPDATE is_completed = VALUES(is_completed),'
            . ' request_id = VALUES(request_id), completed_at = VALUES(completed_at),'
            . ' updated_at = VALUES(updated_at)',
            $userId,
            $courseId,
            $lessonId,
            (int) $taskId,
            $completed ? 1 : 0,
            $requestId,
            $completed ? $occurredAt : '',
            $occurredAt,
            $occurredAt
        );

        if (!is_string($sql) || $this->database->query($sql) === false) {
            return $this->databaseError();
        }

        return (int) $taskId;
    }

    private function rowsOrError(mixed $rows): array|\WP_Error
    {
        if ($this->database->last_error !== '') {
            return $this->databaseError();
        }

        return is_array($rows) ? array_values($rows) : [];
    }

    private function findId(string $query): mixed
    {
        $value = $this->database->get_var($query);

        return $this->database->last_error !== '' ? $this->databaseError() : $value;
    }

    private function notFound(string $message): \WP_Error
    {
        return new \WP_Error('am_toolkit_lesson_task_not_found', $message);
    }

    private function databaseError(): \WP_Error
    {
        return new \WP_Error(
            'am_toolkit_lesson_task_database_failed',
            __('Nie udało się zapisać lub odczytać checklisty lekcji.', 'am-toolkit'),
            ['database_error' => $this->database->last_error]
        );
    }
}
