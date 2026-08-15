<?php

namespace AMToolkit\Modules\Courses;

use AMToolkit\Modules\Access\AccessSchema;
use AMToolkit\Modules\Courses\Contracts\CourseViewStore;
use AMToolkit\Modules\Courses\Domain\Identifier;

defined('ABSPATH') || exit;

final class WpdbCourseViewStore implements CourseViewStore
{
    private \wpdb $database;

    public function __construct(?\wpdb $database = null)
    {
        global $wpdb;

        $this->database = $database ?? $wpdb;
    }

    public function coursesForUser(int $userId, string $at): array|\WP_Error
    {
        $courses = CoursesSchema::coursesTable();
        $grants = AccessSchema::grantsTable();
        $completions = CoursesSchema::completionsTable();
        $rows = $this->database->get_results(
            $this->database->prepare(
                "SELECT c.id, c.public_id, c.title, c.image_attachment_id, c.status AS course_status,
                    MAX(CASE WHEN g.status = 'active'
                        AND (g.starts_at IS NULL OR g.starts_at <= %s)
                        AND (g.expires_at IS NULL OR g.expires_at > %s)
                        THEN 1 ELSE 0 END) AS has_active_access,
                    MAX(CASE WHEN g.status = 'active' AND g.starts_at > %s
                        THEN 1 ELSE 0 END) AS has_future_access,
                    MAX(CASE WHEN cc.id IS NOT NULL THEN 1 ELSE 0 END) AS has_completion,
                    MAX(g.expires_at) AS last_expires_at,
                    MAX(COALESCE(g.revoked_at, g.expires_at)) AS last_access_change
                FROM {$grants} g
                INNER JOIN {$courses} c ON c.id = g.resource_id
                LEFT JOIN {$completions} cc
                    ON cc.user_id = g.user_id AND cc.course_id = c.id
                WHERE g.user_id = %d
                    AND g.resource_type = 'course'
                    AND c.status IN ('published', 'archived')
                GROUP BY c.id, c.public_id, c.title, c.image_attachment_id, c.status
                ORDER BY MAX(g.updated_at) DESC, c.id DESC",
                $at,
                $at,
                $at,
                $userId
            ),
            ARRAY_A
        );

        return $this->rowsOrError($rows);
    }

    public function findPublishedCourse(Identifier $publicId): array|null|\WP_Error
    {
        $courses = CoursesSchema::coursesTable();
        $programs = CoursesSchema::programVersionsTable();
        $row = $this->database->get_row(
            $this->database->prepare(
                "SELECT c.id, c.public_id, c.title, c.description,
                    c.image_attachment_id, c.current_program_version_id
                FROM {$courses} c
                INNER JOIN {$programs} p
                    ON p.id = c.current_program_version_id
                    AND p.course_id = c.id
                    AND p.status = 'published'
                WHERE c.public_id = %s AND c.status = 'published'
                LIMIT 1",
                $publicId->value()
            ),
            ARRAY_A
        );

        if ($this->database->last_error !== '') {
            return $this->databaseError();
        }

        return is_array($row) ? $row : null;
    }

    public function publishedProgram(int $courseId, int $programVersionId): array|\WP_Error
    {
        $programs = CoursesSchema::programVersionsTable();
        $program = $this->database->get_row(
            $this->database->prepare(
                "SELECT public_id, version_number, published_at
                FROM {$programs}
                WHERE id = %d AND course_id = %d AND status = 'published'
                LIMIT 1",
                $programVersionId,
                $courseId
            ),
            ARRAY_A
        );

        if ($this->database->last_error !== '') {
            return $this->databaseError();
        }

        if (!is_array($program)) {
            return $this->databaseError();
        }

        $sectionsTable = CoursesSchema::sectionsTable();
        $sections = $this->database->get_results(
            $this->database->prepare(
                "SELECT id, public_id, title, description, position
                FROM {$sectionsTable}
                WHERE program_version_id = %d AND status = 'published'
                ORDER BY position ASC, id ASC",
                $programVersionId
            ),
            ARRAY_A
        );

        $sectionsResult = $this->rowsOrError($sections);

        if (is_wp_error($sectionsResult)) {
            return $sectionsResult;
        }

        $lessonsTable = CoursesSchema::lessonsTable();
        $programLessons = CoursesSchema::programLessonsTable();
        $lessons = $this->database->get_results(
            $this->database->prepare(
                "SELECT l.public_id, l.title, l.duration_seconds,
                    pl.section_id, pl.position, pl.is_required
                FROM {$programLessons} pl
                INNER JOIN {$lessonsTable} l
                    ON l.id = pl.lesson_id
                    AND l.course_id = %d
                    AND l.status = 'published'
                LEFT JOIN {$sectionsTable} s ON s.id = pl.section_id
                WHERE pl.program_version_id = %d
                    AND (pl.section_id IS NULL OR s.status = 'published')
                ORDER BY COALESCE(s.position, 0) ASC, pl.position ASC, l.id ASC",
                $courseId,
                $programVersionId
            ),
            ARRAY_A
        );

        $lessonsResult = $this->rowsOrError($lessons);

        if (is_wp_error($lessonsResult)) {
            return $lessonsResult;
        }

        $grouped = [];

        foreach ($sectionsResult as $section) {
            $section['lessons'] = [];
            $grouped[(int) $section['id']] = $section;
        }

        $unsectioned = [];

        foreach ($lessonsResult as $lesson) {
            $sectionId = isset($lesson['section_id']) ? (int) $lesson['section_id'] : 0;
            unset($lesson['section_id']);

            if ($sectionId > 0 && isset($grouped[$sectionId])) {
                $grouped[$sectionId]['lessons'][] = $lesson;
            } else {
                $unsectioned[] = $lesson;
            }
        }

        foreach ($grouped as &$section) {
            unset($section['id']);
        }
        unset($section);

        $program['sections'] = array_values($grouped);
        $program['lessons'] = $unsectioned;

        return $program;
    }

    /** @return list<array<string, mixed>>|\WP_Error */
    private function rowsOrError(mixed $rows): array|\WP_Error
    {
        if ($this->database->last_error !== '') {
            return $this->databaseError();
        }

        return is_array($rows) ? $rows : [];
    }

    private function databaseError(): \WP_Error
    {
        return new \WP_Error(
            'am_toolkit_course_view_database_failed',
            __('Nie udało się odczytać katalogu kursów.', 'am-toolkit'),
            ['database_error' => $this->database->last_error]
        );
    }
}
