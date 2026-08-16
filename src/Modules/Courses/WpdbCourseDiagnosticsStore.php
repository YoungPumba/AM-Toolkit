<?php

namespace AMToolkit\Modules\Courses;

use AMToolkit\Modules\Access\AccessSchema;
use AMToolkit\Modules\Courses\Contracts\CourseDiagnosticsStore;

defined('ABSPATH') || exit;

final class WpdbCourseDiagnosticsStore implements CourseDiagnosticsStore
{
    private \wpdb $database;

    public function __construct(?\wpdb $database = null)
    {
        global $wpdb;

        $this->database = $database ?? $wpdb;
    }

    public function schemaHealth(): array|\WP_Error
    {
        $tables = $this->tables();
        $presence = [];

        foreach ($tables as $key => $table) {
            $presence[$key] = $this->tableExists($table);

            if ($this->database->last_error !== '') {
                return $this->databaseError('am_toolkit_course_diagnostics_schema_failed');
            }
        }

        $orphans = [];

        if (!in_array(false, $presence, true)) {
            foreach ($this->orphanQueries($tables) as $key => $query) {
                $count = $this->database->get_var($query);

                if ($this->database->last_error !== '') {
                    return $this->databaseError('am_toolkit_course_diagnostics_schema_failed');
                }

                $orphans[$key] = (int) $count;
            }
        }

        return [
            'checked_at' => current_time('mysql', true),
            'expected_schema_version' => CoursesSchema::VERSION,
            'installed_schema_version' => (int) get_option('am_toolkit_schema_courses', 0),
            'tables' => $presence,
            'orphan_counts' => $orphans,
            'valid' => !in_array(false, $presence, true)
                && (int) get_option('am_toolkit_schema_courses', 0) === CoursesSchema::VERSION
                && array_sum($orphans) === 0,
        ];
    }

    public function snapshot(int $userId, int $courseId): array|\WP_Error
    {
        if ($userId <= 0 || $courseId <= 0) {
            return new \WP_Error(
                'am_toolkit_course_diagnostics_invalid_target',
                __('Wybierz prawidłowego użytkownika i kurs.', 'am-toolkit')
            );
        }

        $programsTable = CoursesSchema::programVersionsTable();
        $course = $this->database->get_row(
            $this->database->prepare(
                "SELECT c.id, c.public_id, c.title, c.status,
                    c.current_program_version_id AS published_program_version_id,
                    (SELECT draft.id FROM {$programsTable} draft
                     WHERE draft.course_id = c.id AND draft.status = 'draft'
                     ORDER BY draft.version_number DESC LIMIT 1) AS draft_program_version_id"
                . ' FROM ' . CoursesSchema::coursesTable() . ' c WHERE c.id = %d LIMIT 1',
                $courseId
            ),
            ARRAY_A
        );

        if ($this->hasDatabaseError()) {
            return $this->databaseError();
        }

        if (!is_array($course)) {
            return new \WP_Error(
                'am_toolkit_course_diagnostics_course_not_found',
                __('Nie znaleziono wybranego kursu.', 'am-toolkit')
            );
        }

        $userExists = (int) $this->database->get_var($this->database->prepare(
            "SELECT ID FROM {$this->database->users} WHERE ID = %d LIMIT 1",
            $userId
        )) > 0;

        $programId = (int) ($course['published_program_version_id'] ?? 0);
        $program = $programId > 0 ? $this->program($programId, $courseId) : null;

        if (is_wp_error($program)) {
            return $program;
        }

        $grants = $this->grants($userId, $courseId);

        if (is_wp_error($grants)) {
            return $grants;
        }

        $lessons = $programId > 0 ? $this->lessons($userId, $courseId, $programId) : [];

        if (is_wp_error($lessons)) {
            return $lessons;
        }

        $completion = $programId > 0 ? $this->completion($userId, $courseId, $programId) : null;

        if (is_wp_error($completion)) {
            return $completion;
        }

        $lastOpened = $this->lastProgress($userId, $courseId, false);
        $lastCompleted = $this->lastProgress($userId, $courseId, true);

        if (is_wp_error($lastOpened)) {
            return $lastOpened;
        }

        if (is_wp_error($lastCompleted)) {
            return $lastCompleted;
        }

        return [
            'user_id' => $userId,
            'user_exists' => $userExists,
            'course' => [
                'id' => (int) $course['id'],
                'public_id' => (string) $course['public_id'],
                'title' => (string) $course['title'],
                'status' => (string) $course['status'],
                'published_program_version_id' => $programId,
                'draft_program_version_id' => (int) ($course['draft_program_version_id'] ?? 0),
            ],
            'program' => $program,
            'grants' => $grants,
            'lessons' => $lessons,
            'completion' => $completion,
            'last_opened_lesson' => $lastOpened,
            'last_completed_lesson' => $lastCompleted,
        ];
    }

    /** @return array<string, string> */
    private function tables(): array
    {
        return [
            'courses' => CoursesSchema::coursesTable(),
            'program_versions' => CoursesSchema::programVersionsTable(),
            'sections' => CoursesSchema::sectionsTable(),
            'lessons' => CoursesSchema::lessonsTable(),
            'program_lessons' => CoursesSchema::programLessonsTable(),
            'materials' => CoursesSchema::materialsTable(),
            'lesson_progress' => CoursesSchema::progressTable(),
            'course_completions' => CoursesSchema::completionsTable(),
            'video_checkpoints' => CoursesSchema::videoCheckpointsTable(),
            'requirement_completions' => CoursesSchema::requirementCompletionsTable(),
            'product_mappings' => CoursesSchema::productMappingsTable(),
            'meetings' => CoursesSchema::meetingsTable(),
            'meeting_revisions' => CoursesSchema::meetingRevisionsTable(),
            'qa_entries' => CoursesSchema::qaEntriesTable(),
            'lesson_tasks' => CoursesSchema::lessonTasksTable(),
            'lesson_task_progress' => CoursesSchema::lessonTaskProgressTable(),
            'access_grants' => AccessSchema::grantsTable(),
            'activity_events' => AccessSchema::eventsTable(),
        ];
    }

    /** @param array<string, string> $tables @return array<string, string> */
    private function orphanQueries(array $tables): array
    {
        $users = $this->database->users;

        return [
            'program_without_course' => "SELECT COUNT(*) FROM {$tables['program_versions']} p LEFT JOIN {$tables['courses']} c ON c.id = p.course_id WHERE c.id IS NULL",
            'section_without_program' => "SELECT COUNT(*) FROM {$tables['sections']} s LEFT JOIN {$tables['program_versions']} p ON p.id = s.program_version_id WHERE p.id IS NULL",
            'lesson_without_course' => "SELECT COUNT(*) FROM {$tables['lessons']} l LEFT JOIN {$tables['courses']} c ON c.id = l.course_id WHERE c.id IS NULL",
            'assignment_without_parent' => "SELECT COUNT(*) FROM {$tables['program_lessons']} pl LEFT JOIN {$tables['program_versions']} p ON p.id = pl.program_version_id LEFT JOIN {$tables['lessons']} l ON l.id = pl.lesson_id LEFT JOIN {$tables['sections']} s ON s.id = pl.section_id WHERE p.id IS NULL OR l.id IS NULL OR (p.id IS NOT NULL AND l.id IS NOT NULL AND p.course_id <> l.course_id) OR (pl.section_id IS NOT NULL AND (s.id IS NULL OR s.program_version_id <> pl.program_version_id))",
            'material_without_lesson' => "SELECT COUNT(*) FROM {$tables['materials']} m LEFT JOIN {$tables['lessons']} l ON l.id = m.lesson_id WHERE l.id IS NULL",
            'progress_without_parent' => "SELECT COUNT(*) FROM {$tables['lesson_progress']} lp LEFT JOIN {$users} u ON u.ID = lp.user_id LEFT JOIN {$tables['courses']} c ON c.id = lp.course_id LEFT JOIN {$tables['lessons']} l ON l.id = lp.lesson_id AND l.course_id = lp.course_id WHERE u.ID IS NULL OR c.id IS NULL OR l.id IS NULL",
            'completion_without_parent' => "SELECT COUNT(*) FROM {$tables['course_completions']} cc LEFT JOIN {$users} u ON u.ID = cc.user_id LEFT JOIN {$tables['courses']} c ON c.id = cc.course_id LEFT JOIN {$tables['program_versions']} p ON p.id = cc.program_version_id AND p.course_id = cc.course_id WHERE u.ID IS NULL OR c.id IS NULL OR p.id IS NULL",
            'checkpoint_without_parent' => "SELECT COUNT(*) FROM {$tables['video_checkpoints']} cp LEFT JOIN {$users} u ON u.ID = cp.user_id LEFT JOIN {$tables['courses']} c ON c.id = cp.course_id LEFT JOIN {$tables['lessons']} l ON l.id = cp.lesson_id AND l.course_id = cp.course_id WHERE u.ID IS NULL OR c.id IS NULL OR l.id IS NULL",
            'requirement_without_parent' => "SELECT COUNT(*) FROM {$tables['requirement_completions']} rc LEFT JOIN {$users} u ON u.ID = rc.user_id LEFT JOIN {$tables['courses']} c ON c.id = rc.course_id LEFT JOIN {$tables['lessons']} l ON l.id = rc.lesson_id AND l.course_id = rc.course_id WHERE u.ID IS NULL OR c.id IS NULL OR l.id IS NULL",
            'mapping_without_course' => "SELECT COUNT(*) FROM {$tables['product_mappings']} pm LEFT JOIN {$tables['courses']} c ON c.id = pm.course_id WHERE c.id IS NULL",
            'meeting_without_course' => "SELECT COUNT(*) FROM {$tables['meetings']} m LEFT JOIN {$tables['courses']} c ON c.id = m.course_id WHERE c.id IS NULL",
            'meeting_revision_without_parent' => "SELECT COUNT(*) FROM {$tables['meeting_revisions']} mr LEFT JOIN {$tables['meetings']} m ON m.id = mr.meeting_id WHERE m.id IS NULL",
            'qa_without_parent' => "SELECT COUNT(*) FROM {$tables['qa_entries']} q LEFT JOIN {$tables['courses']} c ON c.id = q.course_id LEFT JOIN {$tables['lessons']} l ON l.id = q.lesson_id AND l.course_id = q.course_id WHERE c.id IS NULL OR (q.lesson_id IS NOT NULL AND l.id IS NULL)",
            'task_without_parent' => "SELECT COUNT(*) FROM {$tables['lesson_tasks']} t LEFT JOIN {$tables['lessons']} l ON l.id = t.lesson_id WHERE l.id IS NULL",
            'task_progress_without_parent' => "SELECT COUNT(*) FROM {$tables['lesson_task_progress']} tp LEFT JOIN {$users} u ON u.ID = tp.user_id LEFT JOIN {$tables['lesson_tasks']} t ON t.id = tp.task_id LEFT JOIN {$tables['lessons']} l ON l.id = tp.lesson_id LEFT JOIN {$tables['courses']} c ON c.id = tp.course_id WHERE u.ID IS NULL OR t.id IS NULL OR l.id IS NULL OR c.id IS NULL OR (t.id IS NOT NULL AND t.lesson_id <> tp.lesson_id) OR (l.id IS NOT NULL AND l.course_id <> tp.course_id)",
            'course_grant_without_parent' => "SELECT COUNT(*) FROM {$tables['access_grants']} g LEFT JOIN {$users} u ON u.ID = g.user_id LEFT JOIN {$tables['courses']} c ON c.id = g.resource_id WHERE g.resource_type = 'course' AND (u.ID IS NULL OR c.id IS NULL)",
        ];
    }

    /** @return array<string, mixed>|null|\WP_Error */
    private function program(int $programId, int $courseId): array|null|\WP_Error
    {
        $row = $this->database->get_row($this->database->prepare(
            'SELECT id, version_number, status, published_at, created_at FROM '
            . CoursesSchema::programVersionsTable() . ' WHERE id = %d AND course_id = %d LIMIT 1',
            $programId,
            $courseId
        ), ARRAY_A);

        if ($this->database->last_error !== '') {
            return $this->databaseError();
        }

        if (!is_array($row)) {
            return null;
        }

        return [
            'id' => (int) $row['id'],
            'version_number' => (int) $row['version_number'],
            'status' => (string) $row['status'],
            'published_at' => (string) ($row['published_at'] ?? ''),
            'created_at' => (string) ($row['created_at'] ?? ''),
        ];
    }

    /** @return list<array<string, mixed>>|\WP_Error */
    private function grants(int $userId, int $courseId): array|\WP_Error
    {
        $now = current_time('mysql', true);
        $rows = $this->database->get_results($this->database->prepare(
            'SELECT id, source_type, source_id, status, starts_at, expires_at, granted_at, revoked_at,'
            . " CASE WHEN status = 'active' AND (starts_at IS NULL OR starts_at <= %s)"
            . ' AND (expires_at IS NULL OR expires_at > %s) THEN 1 ELSE 0 END AS is_active'
            . ' FROM ' . AccessSchema::grantsTable()
            . " WHERE user_id = %d AND resource_type = 'course' AND resource_id = %d"
            . ' ORDER BY updated_at DESC, id DESC',
            $now,
            $now,
            $userId,
            $courseId
        ), ARRAY_A);

        if (!is_array($rows)) {
            return $this->databaseError();
        }

        return array_map(static fn (array $row): array => [
            'id' => (int) $row['id'],
            'source_type' => (string) $row['source_type'],
            'source_id' => (int) $row['source_id'],
            'status' => (string) $row['status'],
            'starts_at' => (string) ($row['starts_at'] ?? ''),
            'expires_at' => (string) ($row['expires_at'] ?? ''),
            'granted_at' => (string) ($row['granted_at'] ?? ''),
            'revoked_at' => (string) ($row['revoked_at'] ?? ''),
            'is_active' => (bool) $row['is_active'],
        ], $rows);
    }

    /** @return list<array<string, mixed>>|\WP_Error */
    private function lessons(int $userId, int $courseId, int $programId): array|\WP_Error
    {
        $rows = $this->database->get_results($this->database->prepare(
            'SELECT l.id, l.public_id, l.title, l.content_version, pl.is_required,'
            . ' lp.status AS progress_status, lp.content_version AS progress_content_version,'
            . ' lp.completion_source, lp.request_id, lp.completed_at, lp.updated_at'
            . ' FROM ' . CoursesSchema::programLessonsTable() . ' pl'
            . ' INNER JOIN ' . CoursesSchema::lessonsTable() . " l ON l.id = pl.lesson_id AND l.course_id = %d AND l.status = 'published'"
            . ' LEFT JOIN ' . CoursesSchema::sectionsTable() . ' s ON s.id = pl.section_id AND s.program_version_id = pl.program_version_id'
            . ' LEFT JOIN ' . CoursesSchema::progressTable() . ' lp ON lp.user_id = %d AND lp.course_id = %d AND lp.lesson_id = l.id'
            . " WHERE pl.program_version_id = %d AND (pl.section_id IS NULL OR s.status = 'published')"
            . ' ORDER BY COALESCE(s.position, 2147483647), pl.position, l.id',
            $courseId,
            $userId,
            $courseId,
            $programId
        ), ARRAY_A);

        if (!is_array($rows)) {
            return $this->databaseError();
        }

        return array_map(static fn (array $row): array => [
            'id' => (int) $row['id'],
            'public_id' => (string) $row['public_id'],
            'title' => (string) $row['title'],
            'content_version' => (int) $row['content_version'],
            'is_required' => (bool) $row['is_required'],
            'progress_status' => (string) ($row['progress_status'] ?? ''),
            'progress_content_version' => (int) ($row['progress_content_version'] ?? 0),
            'completion_source' => (string) ($row['completion_source'] ?? ''),
            'request_id' => (string) ($row['request_id'] ?? ''),
            'completed_at' => (string) ($row['completed_at'] ?? ''),
            'updated_at' => (string) ($row['updated_at'] ?? ''),
        ], $rows);
    }

    /** @return array<string, mixed>|null|\WP_Error */
    private function completion(int $userId, int $courseId, int $programId): array|null|\WP_Error
    {
        $row = $this->database->get_row($this->database->prepare(
            'SELECT id, required_lesson_ids, requirements_hash, completion_source, request_id, completed_at'
            . ' FROM ' . CoursesSchema::completionsTable()
            . ' WHERE user_id = %d AND course_id = %d AND program_version_id = %d LIMIT 1',
            $userId,
            $courseId,
            $programId
        ), ARRAY_A);

        if ($this->database->last_error !== '') {
            return $this->databaseError();
        }

        if (!is_array($row)) {
            return null;
        }

        $lessonIds = json_decode((string) $row['required_lesson_ids'], true);

        return [
            'id' => (int) $row['id'],
            'required_lesson_ids' => array_values(array_map('intval', is_array($lessonIds) ? $lessonIds : [])),
            'required_lesson_ids_valid' => is_array($lessonIds),
            'requirements_hash' => (string) $row['requirements_hash'],
            'completion_source' => (string) $row['completion_source'],
            'request_id' => (string) ($row['request_id'] ?? ''),
            'completed_at' => (string) $row['completed_at'],
        ];
    }

    /** @return array<string, mixed>|null|\WP_Error */
    private function lastProgress(int $userId, int $courseId, bool $completed): array|null|\WP_Error
    {
        $status = $completed ? "AND lp.status = 'completed'" : '';
        $order = $completed ? 'COALESCE(lp.completed_at, lp.updated_at)' : 'lp.updated_at';
        $row = $this->database->get_row($this->database->prepare(
            'SELECT l.id, l.public_id, l.title, lp.status, lp.request_id, lp.updated_at, lp.completed_at'
            . ' FROM ' . CoursesSchema::progressTable() . ' lp'
            . ' INNER JOIN ' . CoursesSchema::lessonsTable() . ' l ON l.id = lp.lesson_id AND l.course_id = lp.course_id'
            . " WHERE lp.user_id = %d AND lp.course_id = %d {$status} ORDER BY {$order} DESC, lp.id DESC LIMIT 1",
            $userId,
            $courseId
        ), ARRAY_A);

        if ($this->database->last_error !== '') {
            return $this->databaseError();
        }

        if (!is_array($row)) {
            return null;
        }

        return [
            'id' => (int) $row['id'],
            'public_id' => (string) $row['public_id'],
            'title' => (string) $row['title'],
            'status' => (string) $row['status'],
            'request_id' => (string) ($row['request_id'] ?? ''),
            'updated_at' => (string) ($row['updated_at'] ?? ''),
            'completed_at' => (string) ($row['completed_at'] ?? ''),
        ];
    }

    private function tableExists(string $table): bool
    {
        return $this->database->get_var(
            $this->database->prepare('SHOW TABLES LIKE %s', $this->database->esc_like($table))
        ) === $table;
    }

    private function databaseError(string $code = 'am_toolkit_course_diagnostics_read_failed'): \WP_Error
    {
        return new \WP_Error(
            $code,
            __('Nie udało się odczytać diagnostyki kursów.', 'am-toolkit'),
            ['database_error' => $this->database->last_error]
        );
    }

    private function hasDatabaseError(): bool
    {
        return $this->database->last_error !== '';
    }
}
