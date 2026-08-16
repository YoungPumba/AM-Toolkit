<?php

namespace AMToolkit\Modules\Courses;

use AMToolkit\Modules\Courses\Contracts\CourseQaStore;

defined('ABSPATH') || exit;

final class WpdbCourseQaStore implements CourseQaStore
{
    private \wpdb $database;

    public function __construct(?\wpdb $database = null)
    {
        global $wpdb;
        $this->database = $database ?? $wpdb;
    }

    public function entriesForCourse(int $courseId): array|\WP_Error
    {
        $entries = CoursesSchema::qaEntriesTable();
        $lessons = CoursesSchema::lessonsTable();
        $rows = $this->database->get_results(
            $this->database->prepare(
                "SELECT q.id, q.public_id, q.course_id, q.lesson_id, q.question, q.answer, q.position, q.status,
                    q.updated_at, l.title AS lesson_title
                FROM {$entries} q
                LEFT JOIN {$lessons} l ON l.id = q.lesson_id AND l.course_id = q.course_id
                WHERE q.course_id = %d
                ORDER BY q.position ASC, q.id ASC",
                $courseId
            ),
            ARRAY_A
        );

        return $this->rowsOrError($rows);
    }

    public function publishedEntriesForCourse(int $courseId, int $programVersionId): array|\WP_Error
    {
        $entries = CoursesSchema::qaEntriesTable();
        $lessons = CoursesSchema::lessonsTable();
        $programLessons = CoursesSchema::programLessonsTable();
        $rows = $this->database->get_results(
            $this->database->prepare(
                "SELECT q.public_id, q.question, q.answer, q.position,
                    CASE WHEN l.status = 'published' AND pl.lesson_id IS NOT NULL THEN l.public_id ELSE NULL END AS lesson_public_id,
                    CASE WHEN l.status = 'published' AND pl.lesson_id IS NOT NULL THEN l.title ELSE NULL END AS lesson_title
                FROM {$entries} q
                LEFT JOIN {$lessons} l ON l.id = q.lesson_id AND l.course_id = q.course_id AND l.archived_at IS NULL
                LEFT JOIN {$programLessons} pl ON pl.lesson_id = l.id AND pl.program_version_id = %d
                WHERE q.course_id = %d AND q.status = 'published' AND q.archived_at IS NULL
                ORDER BY q.position ASC, q.id ASC",
                $programVersionId,
                $courseId
            ),
            ARRAY_A
        );

        return $this->rowsOrError($rows);
    }

    public function saveEntry(array $entry): int|\WP_Error
    {
        $entryId = max(0, (int) ($entry['id'] ?? 0));
        $courseId = (int) ($entry['course_id'] ?? 0);
        $lessonId = isset($entry['lesson_id']) ? (int) $entry['lesson_id'] : null;
        $table = CoursesSchema::qaEntriesTable();
        $courseExists = $this->findId($this->database->prepare(
            'SELECT id FROM ' . CoursesSchema::coursesTable() . ' WHERE id = %d LIMIT 1',
            $courseId
        ));

        if (is_wp_error($courseExists)) {
            return $courseExists;
        }

        if ($courseExists === null) {
            return new \WP_Error('am_toolkit_course_qa_course_invalid', __('Nie znaleziono kursu dla tego wpisu Q&A.', 'am-toolkit'));
        }

        if ($lessonId !== null) {
            $belongs = $this->findId($this->database->prepare(
                'SELECT id FROM ' . CoursesSchema::lessonsTable() . ' WHERE id = %d AND course_id = %d LIMIT 1',
                $lessonId,
                $courseId
            ));

            if (is_wp_error($belongs)) {
                return $belongs;
            }

            if ($belongs === null) {
                return new \WP_Error('am_toolkit_course_qa_lesson_invalid', __('Wybrana lekcja nie należy do tego kursu.', 'am-toolkit'));
            }
        }

        if ($entryId > 0) {
            $existing = $this->findId($this->database->prepare(
                "SELECT id FROM {$table} WHERE id = %d AND course_id = %d LIMIT 1",
                $entryId,
                $courseId
            ));

            if (is_wp_error($existing)) {
                return $existing;
            }

            if ($existing === null) {
                return new \WP_Error('am_toolkit_course_qa_not_found', __('Nie znaleziono tego wpisu Q&A w kursie.', 'am-toolkit'));
            }
        }

        $now = current_time('mysql', true);
        $status = (string) $entry['status'];
        $data = [
            'course_id' => $courseId,
            'lesson_id' => $lessonId,
            'question' => (string) $entry['question'],
            'answer' => (string) $entry['answer'],
            'position' => (int) $entry['position'],
            'status' => $status,
            'updated_at' => $now,
            'archived_at' => $status === 'archived' ? $now : null,
        ];

        if ($entryId > 0) {
            $saved = $this->database->update($table, $data, ['id' => $entryId, 'course_id' => $courseId]);
            return $saved === false ? $this->databaseError() : $entryId;
        }

        $data += ['public_id' => wp_generate_uuid4(), 'created_at' => $now];
        if ($this->database->insert($table, $data) !== 1) {
            return $this->databaseError();
        }

        return (int) $this->database->insert_id;
    }

    public function archiveEntry(int $entryId, int $courseId): bool|\WP_Error
    {
        $result = $this->database->update(
            CoursesSchema::qaEntriesTable(),
            ['status' => 'archived', 'archived_at' => current_time('mysql', true), 'updated_at' => current_time('mysql', true)],
            ['id' => $entryId, 'course_id' => $courseId]
        );

        if ($result === false) {
            return $this->databaseError();
        }

        return $result === 1
            ? true
            : new \WP_Error('am_toolkit_course_qa_not_found', __('Nie znaleziono tego wpisu Q&A w kursie.', 'am-toolkit'));
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

    private function databaseError(): \WP_Error
    {
        return new \WP_Error(
            'am_toolkit_course_qa_database_failed',
            __('Nie udało się zapisać lub odczytać sekcji Q&A.', 'am-toolkit'),
            ['database_error' => $this->database->last_error]
        );
    }
}
