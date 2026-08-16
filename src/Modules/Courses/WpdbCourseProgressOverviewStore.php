<?php

namespace AMToolkit\Modules\Courses;

use AMToolkit\Modules\Courses\Contracts\CourseProgressOverviewStore;

defined('ABSPATH') || exit;

final class WpdbCourseProgressOverviewStore implements CourseProgressOverviewStore
{
    private \wpdb $database;

    public function __construct(?\wpdb $database = null)
    {
        global $wpdb;

        $this->database = $database ?? $wpdb;
    }

    public function lessons(int $userId, int $courseId, int $programVersionId): array|\WP_Error
    {
        $assignments = CoursesSchema::programLessonsTable();
        $lessons = CoursesSchema::lessonsTable();
        $sections = CoursesSchema::sectionsTable();
        $progress = CoursesSchema::progressTable();
        $rows = $this->database->get_results(
            $this->database->prepare(
                "SELECT l.public_id, pl.is_required,
                    CASE WHEN lp.content_version = l.content_version THEN lp.status ELSE NULL END AS progress_status,
                    CASE WHEN lp.content_version = l.content_version THEN lp.updated_at ELSE NULL END AS progress_updated_at
                FROM {$assignments} pl
                INNER JOIN {$lessons} l
                    ON l.id = pl.lesson_id AND l.course_id = %d AND l.status = 'published'
                LEFT JOIN {$sections} s
                    ON s.id = pl.section_id AND s.program_version_id = pl.program_version_id
                LEFT JOIN {$progress} lp
                    ON lp.user_id = %d AND lp.course_id = %d AND lp.lesson_id = l.id
                WHERE pl.program_version_id = %d
                    AND (pl.section_id IS NULL OR s.status = 'published')
                ORDER BY COALESCE(s.position, 2147483647) ASC, pl.position ASC, l.id ASC",
                $courseId,
                $userId,
                $courseId,
                $programVersionId
            ),
            ARRAY_A
        );

        if (!is_array($rows)) {
            return $this->databaseError();
        }

        return $rows;
    }

    public function hasCompletion(int $userId, int $courseId, int $programVersionId): bool|\WP_Error
    {
        $id = $this->database->get_var(
            $this->database->prepare(
                'SELECT id FROM ' . CoursesSchema::completionsTable()
                . ' WHERE user_id = %d AND course_id = %d LIMIT 1',
                $userId,
                $courseId
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
            'am_toolkit_course_progress_overview_failed',
            __('Nie udało się odczytać podsumowania postępu kursu.', 'am-toolkit'),
            ['database_error' => $this->database->last_error]
        );
    }
}
