<?php

namespace AMToolkit\Modules\Courses;

use AMToolkit\Modules\Access\AccessSchema;
use AMToolkit\Modules\Courses\Contracts\CourseAdminStore;
use AMToolkit\Modules\Courses\Contracts\DraftCourseResourceDeletionStore;
use AMToolkit\Modules\Courses\Domain\PublicationStatus;

defined('ABSPATH') || exit;

final class WpdbCourseAdminStore implements CourseAdminStore, DraftCourseResourceDeletionStore
{
    private \wpdb $database;

    public function __construct(?\wpdb $database = null)
    {
        global $wpdb;

        $this->database = $database ?? $wpdb;
    }

    public function listCourses(): array|\WP_Error
    {
        $courses = CoursesSchema::coursesTable();
        $programs = CoursesSchema::programVersionsTable();
        $rows = $this->database->get_results(
            "SELECT c.*, p.version_number AS current_version_number,
                    p.status AS current_program_status
            FROM {$courses} c
            LEFT JOIN {$programs} p ON p.id = c.current_program_version_id
            ORDER BY (c.status = 'archived') ASC, c.updated_at DESC, c.id DESC",
            ARRAY_A
        );

        return $this->rowsOrError($rows, 'am_toolkit_course_admin_read_failed');
    }

    public function findCourse(int $courseId): array|null|\WP_Error
    {
        $courses = CoursesSchema::coursesTable();
        $programs = CoursesSchema::programVersionsTable();
        $row = $this->database->get_row(
            $this->database->prepare(
                "SELECT c.*, p.version_number AS current_version_number,
                        p.status AS current_program_status,
                        (SELECT id FROM {$programs}
                         WHERE course_id = c.id AND status = 'draft'
                         ORDER BY version_number DESC LIMIT 1) AS draft_program_version_id
                FROM {$courses} c
                LEFT JOIN {$programs} p ON p.id = c.current_program_version_id
                WHERE c.id = %d LIMIT 1",
                $courseId
            ),
            ARRAY_A
        );

        if ($this->database->last_error !== '') {
            return $this->databaseError('am_toolkit_course_admin_read_failed');
        }

        return is_array($row) ? $row : null;
    }

    public function saveCourse(
        int $courseId,
        string $title,
        string $description,
        int $imageAttachmentId,
        string $status
    ): int|\WP_Error {
        $courses = CoursesSchema::coursesTable();
        $now = current_time('mysql', true);
        $this->begin();

        if ($courseId <= 0) {
            $created = $this->database->insert(
                $courses,
                [
                    'public_id' => wp_generate_uuid4(),
                    'title' => $title,
                    'description' => $description,
                    'image_attachment_id' => $imageAttachmentId,
                    'status' => PublicationStatus::DRAFT,
                    'current_program_version_id' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                    'archived_at' => null,
                ]
            );

            if ($created !== 1) {
                return $this->rollbackError('am_toolkit_course_admin_write_failed');
            }

            $courseId = (int) $this->database->insert_id;
            $programId = $this->createProgram($courseId, 1, PublicationStatus::DRAFT, null, hash('sha256', '[]'));

            if (is_wp_error($programId)) {
                $this->rollback();
                return $programId;
            }

            if ($this->database->update(
                $courses,
                ['current_program_version_id' => $programId],
                ['id' => $courseId],
                ['%d'],
                ['%d']
            ) === false) {
                return $this->rollbackError('am_toolkit_course_admin_write_failed');
            }
        } else {
            if (!$this->courseExists($courseId)) {
                $this->rollback();
                return $this->notFound();
            }

            if ($this->database->update(
                $courses,
                [
                    'title' => $title,
                    'description' => $description,
                    'image_attachment_id' => $imageAttachmentId,
                    'updated_at' => $now,
                ],
                ['id' => $courseId]
            ) === false) {
                return $this->rollbackError('am_toolkit_course_admin_write_failed');
            }
        }

        if ($status === PublicationStatus::PUBLISHED) {
            $published = $this->publishDraft($courseId, $now);

            if (is_wp_error($published)) {
                $this->rollback();
                return $published;
            }
        } elseif ($status === PublicationStatus::ARCHIVED) {
            if ($this->setCourseStatus($courseId, PublicationStatus::ARCHIVED, $now, $now) === false) {
                return $this->rollbackError('am_toolkit_course_admin_write_failed');
            }
        } else {
            $currentStatus = (string) $this->database->get_var(
                $this->database->prepare("SELECT status FROM {$courses} WHERE id = %d", $courseId)
            );

            if ($currentStatus !== PublicationStatus::PUBLISHED) {
                if ($this->setCourseStatus($courseId, PublicationStatus::DRAFT, $now, null) === false) {
                    return $this->rollbackError('am_toolkit_course_admin_write_failed');
                }
            }
        }

        $this->commit();

        return $courseId;
    }

    public function archiveCourse(int $courseId): bool|\WP_Error
    {
        if (!$this->courseExists($courseId)) {
            return $this->notFound();
        }

        $now = current_time('mysql', true);
        $result = $this->setCourseStatus($courseId, PublicationStatus::ARCHIVED, $now, $now);

        return $result === false
            ? $this->databaseError('am_toolkit_course_admin_write_failed')
            : true;
    }

    public function deleteDraftResource(string $resourceType, int $resourceId, int $courseId): bool|\WP_Error
    {
        return match ($resourceType) {
            'course' => $this->deleteDraftCourse($courseId),
            'section' => $this->deleteDraftSection($resourceId, $courseId),
            'lesson' => $this->deleteDraftLesson($resourceId, $courseId),
            'material' => $this->deleteDraftMaterial($resourceId, $courseId),
            default => $this->unsafeDelete(),
        };
    }

    public function sectionsForCourse(int $courseId): array|\WP_Error
    {
        $programId = $this->workspaceProgramId($courseId);

        if (is_wp_error($programId)) {
            return $programId;
        }

        $sections = CoursesSchema::sectionsTable();
        $rows = $this->database->get_results(
            $this->database->prepare(
                "SELECT * FROM {$sections}
                WHERE program_version_id = %d
                ORDER BY position ASC, id ASC",
                $programId
            ),
            ARRAY_A
        );

        return $this->rowsOrError($rows, 'am_toolkit_course_admin_read_failed');
    }

    public function saveSection(
        int $sectionId,
        int $courseId,
        string $title,
        string $description,
        int $position,
        string $status
    ): int|\WP_Error {
        $programId = $this->draftProgramId($courseId);

        if (is_wp_error($programId)) {
            return $programId;
        }

        $sections = CoursesSchema::sectionsTable();
        $now = current_time('mysql', true);
        $archivedAt = $status === PublicationStatus::ARCHIVED ? $now : null;
        $this->begin();

        if ($sectionId > 0) {
            if (!$this->sectionBelongsToProgram($sectionId, $programId)) {
                $this->rollback();
                return $this->notFound();
            }

            $result = $this->database->update(
                $sections,
                [
                    'title' => $title,
                    'description' => $description,
                    'status' => $status,
                    'updated_at' => $now,
                    'archived_at' => $archivedAt,
                ],
                ['id' => $sectionId]
            );

            if ($result === false) {
                return $this->rollbackError('am_toolkit_course_admin_write_failed');
            }
        } else {
            $temporaryPosition = ((int) $this->database->get_var(
                $this->database->prepare(
                    "SELECT COALESCE(MAX(position), 0) FROM {$sections} WHERE program_version_id = %d",
                    $programId
                )
            )) + 1000;
            $result = $this->database->insert(
                $sections,
                [
                    'public_id' => wp_generate_uuid4(),
                    'program_version_id' => $programId,
                    'title' => $title,
                    'description' => $description,
                    'position' => $temporaryPosition,
                    'status' => $status,
                    'created_at' => $now,
                    'updated_at' => $now,
                    'archived_at' => $archivedAt,
                ]
            );

            if ($result !== 1) {
                return $this->rollbackError('am_toolkit_course_admin_write_failed');
            }

            $sectionId = (int) $this->database->insert_id;
        }

        if (!$this->reorderSections($programId, $sectionId, $position)) {
            return $this->rollbackError('am_toolkit_course_admin_write_failed');
        }

        $this->commit();

        return $sectionId;
    }

    public function archiveSection(int $sectionId, int $courseId): bool|\WP_Error
    {
        $programId = $this->draftProgramId($courseId);

        if (is_wp_error($programId) || !$this->sectionBelongsToProgram($sectionId, $programId)) {
            return is_wp_error($programId) ? $programId : $this->notFound();
        }

        $now = current_time('mysql', true);
        $result = $this->database->update(
            CoursesSchema::sectionsTable(),
            ['status' => PublicationStatus::ARCHIVED, 'archived_at' => $now, 'updated_at' => $now],
            ['id' => $sectionId]
        );

        return $result === false
            ? $this->databaseError('am_toolkit_course_admin_write_failed')
            : true;
    }

    public function lessonsForCourse(int $courseId): array|\WP_Error
    {
        $programId = $this->workspaceProgramId($courseId);

        if (is_wp_error($programId)) {
            return $programId;
        }

        $lessons = CoursesSchema::lessonsTable();
        $assignments = CoursesSchema::programLessonsTable();
        $sections = CoursesSchema::sectionsTable();
        $rows = $this->database->get_results(
            $this->database->prepare(
                "SELECT l.*, pl.section_id, pl.position, pl.is_required,
                        s.title AS section_title
                FROM {$lessons} l
                INNER JOIN {$assignments} pl ON pl.lesson_id = l.id
                LEFT JOIN {$sections} s ON s.id = pl.section_id
                WHERE l.course_id = %d AND pl.program_version_id = %d
                ORDER BY COALESCE(s.position, 0) ASC, pl.position ASC, l.id ASC",
                $courseId,
                $programId
            ),
            ARRAY_A
        );

        $rows = $this->rowsOrError($rows, 'am_toolkit_course_admin_read_failed');

        if (is_wp_error($rows)) {
            return $rows;
        }

        foreach ($rows as &$row) {
            $requirements = json_decode((string) ($row['completion_requirements'] ?? ''), true);
            $row['completion_requirements'] = is_array($requirements) ? $requirements : [];
        }
        unset($row);

        return $rows;
    }

    public function saveLesson(
        int $lessonId,
        int $courseId,
        ?int $sectionId,
        string $title,
        string $description,
        ?string $videoProvider,
        ?string $videoReference,
        ?int $durationSeconds,
        array $completionRequirements,
        int $position,
        bool $required,
        string $status
    ): int|\WP_Error {
        $programId = $this->draftProgramId($courseId);

        if (is_wp_error($programId)) {
            return $programId;
        }

        if ($sectionId !== null && !$this->sectionBelongsToProgram($sectionId, $programId)) {
            return $this->notFound();
        }

        $requirements = wp_json_encode($completionRequirements);

        if ($requirements === false) {
            return new \WP_Error(
                'am_toolkit_course_requirements_invalid',
                __('Nie udało się zapisać wymagań ukończenia lekcji.', 'am-toolkit')
            );
        }

        $lessons = CoursesSchema::lessonsTable();
        $assignments = CoursesSchema::programLessonsTable();
        $now = current_time('mysql', true);
        $archivedAt = $status === PublicationStatus::ARCHIVED ? $now : null;
        $this->begin();

        if ($lessonId > 0) {
            $existing = $this->database->get_row(
                $this->database->prepare(
                    "SELECT id, content_version FROM {$lessons} WHERE id = %d AND course_id = %d",
                    $lessonId,
                    $courseId
                ),
                ARRAY_A
            );

            if (!is_array($existing)) {
                $this->rollback();
                return $this->notFound();
            }

            $result = $this->database->update(
                $lessons,
                [
                    'title' => $title,
                    'description' => $description,
                    'status' => $status,
                    'video_provider' => $videoProvider,
                    'video_reference' => $videoReference,
                    'duration_seconds' => $durationSeconds,
                    'completion_requirements' => $requirements,
                    'content_version' => ((int) $existing['content_version']) + 1,
                    'updated_at' => $now,
                    'archived_at' => $archivedAt,
                ],
                ['id' => $lessonId]
            );

            if ($result === false) {
                return $this->rollbackError('am_toolkit_course_admin_write_failed');
            }
        } else {
            $result = $this->database->insert(
                $lessons,
                [
                    'public_id' => wp_generate_uuid4(),
                    'course_id' => $courseId,
                    'title' => $title,
                    'description' => $description,
                    'status' => $status,
                    'video_provider' => $videoProvider,
                    'video_reference' => $videoReference,
                    'duration_seconds' => $durationSeconds,
                    'completion_requirements' => $requirements,
                    'content_version' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                    'archived_at' => $archivedAt,
                ]
            );

            if ($result !== 1) {
                return $this->rollbackError('am_toolkit_course_admin_write_failed');
            }

            $lessonId = (int) $this->database->insert_id;
        }

        $sql = $this->database->prepare(
            "INSERT INTO {$assignments}
                (program_version_id, lesson_id, section_id, position, is_required)
            VALUES (%d, %d, NULLIF(%d, 0), %d, %d)
            ON DUPLICATE KEY UPDATE
                section_id = VALUES(section_id),
                position = VALUES(position),
                is_required = VALUES(is_required)",
            $programId,
            $lessonId,
            $sectionId ?? 0,
            $position,
            $required ? 1 : 0
        );

        if ($this->database->query($sql) === false) {
            return $this->rollbackError('am_toolkit_course_admin_write_failed');
        }

        $this->commit();

        return $lessonId;
    }

    public function archiveLesson(int $lessonId, int $courseId): bool|\WP_Error
    {
        $now = current_time('mysql', true);
        $result = $this->database->update(
            CoursesSchema::lessonsTable(),
            ['status' => PublicationStatus::ARCHIVED, 'archived_at' => $now, 'updated_at' => $now],
            ['id' => $lessonId, 'course_id' => $courseId]
        );

        return $result === false
            ? $this->databaseError('am_toolkit_course_admin_write_failed')
            : ($result > 0 ? true : $this->notFound());
    }

    public function materialsForCourse(int $courseId): array|\WP_Error
    {
        $materials = CoursesSchema::materialsTable();
        $lessons = CoursesSchema::lessonsTable();
        $rows = $this->database->get_results(
            $this->database->prepare(
                "SELECT m.*, l.title AS lesson_title
                FROM {$materials} m
                INNER JOIN {$lessons} l ON l.id = m.lesson_id
                WHERE l.course_id = %d
                ORDER BY l.id ASC, m.position ASC, m.id ASC",
                $courseId
            ),
            ARRAY_A
        );

        return $this->rowsOrError($rows, 'am_toolkit_course_admin_read_failed');
    }

    public function saveMaterial(
        int $materialId,
        int $lessonId,
        string $name,
        string $description,
        string $storageProvider,
        string $storageReference,
        int $position,
        string $status
    ): int|\WP_Error {
        if (!$this->lessonExists($lessonId)) {
            return $this->notFound();
        }

        $materials = CoursesSchema::materialsTable();
        $now = current_time('mysql', true);
        $data = [
            'name' => $name,
            'description' => $description,
            'storage_provider' => $storageProvider,
            'storage_reference' => $storageReference,
            'position' => $position,
            'status' => $status,
            'updated_at' => $now,
            'archived_at' => $status === PublicationStatus::ARCHIVED ? $now : null,
        ];

        if ($materialId > 0) {
            if (!$this->materialBelongsToLesson($materialId, $lessonId)) {
                return $this->notFound();
            }

            $result = $this->database->update(
                $materials,
                $data,
                ['id' => $materialId, 'lesson_id' => $lessonId]
            );

            return $result === false
                ? $this->databaseError('am_toolkit_course_admin_write_failed')
                : $materialId;
        }

        $data += [
            'public_id' => wp_generate_uuid4(),
            'lesson_id' => $lessonId,
            'created_at' => $now,
        ];
        $result = $this->database->insert($materials, $data);

        return $result === 1
            ? (int) $this->database->insert_id
            : $this->databaseError('am_toolkit_course_admin_write_failed');
    }

    public function archiveMaterial(int $materialId, int $lessonId): bool|\WP_Error
    {
        $now = current_time('mysql', true);
        $result = $this->database->update(
            CoursesSchema::materialsTable(),
            ['status' => PublicationStatus::ARCHIVED, 'archived_at' => $now, 'updated_at' => $now],
            ['id' => $materialId, 'lesson_id' => $lessonId]
        );

        return $result === false
            ? $this->databaseError('am_toolkit_course_admin_write_failed')
            : ($result > 0 ? true : $this->notFound());
    }

    public function participantsForCourse(int $courseId): array|\WP_Error
    {
        $grants = AccessSchema::grantsTable();
        $users = $this->database->users;
        $rows = $this->database->get_results(
            $this->database->prepare(
                "SELECT g.id, g.user_id, g.source_type, g.source_id, g.status,
                        g.granted_at, g.revoked_at, u.user_login, u.display_name
                FROM {$grants} g
                INNER JOIN {$users} u ON u.ID = g.user_id
                WHERE g.resource_type = 'course' AND g.resource_id = %d
                ORDER BY (g.status = 'active') DESC, g.updated_at DESC, g.id DESC",
                $courseId
            ),
            ARRAY_A
        );

        return $this->rowsOrError($rows, 'am_toolkit_course_admin_read_failed');
    }

    public function activityForCourse(int $courseId, int $limit = 50): array|\WP_Error
    {
        $events = AccessSchema::eventsTable();
        $rows = $this->database->get_results(
            $this->database->prepare(
                "SELECT id, event_type, user_id, actor_id, request_id, occurred_at
                FROM {$events}
                WHERE object_type = 'course' AND object_id = %d
                ORDER BY occurred_at DESC, id DESC LIMIT %d",
                $courseId,
                max(1, min(200, $limit))
            ),
            ARRAY_A
        );

        return $this->rowsOrError($rows, 'am_toolkit_course_admin_read_failed');
    }

    private function deleteDraftCourse(int $courseId): bool|\WP_Error
    {
        $courses = CoursesSchema::coursesTable();
        $programs = CoursesSchema::programVersionsTable();
        $row = $this->database->get_row(
            $this->database->prepare(
                "SELECT id FROM {$courses} WHERE id = %d AND status = 'draft'"
                . ' AND created_at = updated_at LIMIT 1',
                $courseId
            ),
            ARRAY_A
        );

        if ($this->database->last_error !== '') {
            return $this->databaseError('am_toolkit_course_admin_delete_failed');
        }

        if (!is_array($row) || $this->courseHasDeletionDependencies($courseId)) {
            return $this->unsafeDelete();
        }

        $this->begin();
        $deletedPrograms = $this->database->query($this->database->prepare(
            "DELETE FROM {$programs} WHERE course_id = %d AND status = 'draft'",
            $courseId
        ));
        $deletedCourse = $this->database->delete($courses, ['id' => $courseId], ['%d']);

        if ($deletedPrograms === false || $deletedCourse !== 1) {
            return $this->rollbackError('am_toolkit_course_admin_delete_failed');
        }

        $this->commit();
        return true;
    }

    private function deleteDraftSection(int $sectionId, int $courseId): bool|\WP_Error
    {
        $programId = $this->draftProgramId($courseId);
        if (is_wp_error($programId)) {
            return $programId;
        }

        $sections = CoursesSchema::sectionsTable();
        $assignments = CoursesSchema::programLessonsTable();
        $safe = $this->database->get_var($this->database->prepare(
            "SELECT s.id FROM {$sections} s"
            . " WHERE s.id = %d AND s.program_version_id = %d AND s.status = 'draft'"
            . ' AND s.created_at = s.updated_at'
            . " AND NOT EXISTS (SELECT 1 FROM {$assignments} pl WHERE pl.section_id = s.id)"
            . ' LIMIT 1',
            $sectionId,
            $programId
        ));

        if ($this->database->last_error !== '') {
            return $this->databaseError('am_toolkit_course_admin_delete_failed');
        }

        if ($safe === null) {
            return $this->unsafeDelete();
        }

        return $this->database->delete($sections, ['id' => $sectionId], ['%d']) === 1
            ? true
            : $this->databaseError('am_toolkit_course_admin_delete_failed');
    }

    private function deleteDraftLesson(int $lessonId, int $courseId): bool|\WP_Error
    {
        $lessons = CoursesSchema::lessonsTable();
        $programs = CoursesSchema::programVersionsTable();
        $assignments = CoursesSchema::programLessonsTable();
        $safe = $this->database->get_var($this->database->prepare(
            "SELECT l.id FROM {$lessons} l"
            . " WHERE l.id = %d AND l.course_id = %d AND l.status = 'draft'"
            . ' AND l.created_at = l.updated_at'
            . " AND NOT EXISTS (SELECT 1 FROM {$assignments} published_assignment"
            . " INNER JOIN {$programs} published_program ON published_program.id = published_assignment.program_version_id"
            . " WHERE published_assignment.lesson_id = l.id AND published_program.status = 'published')"
            . ' AND NOT EXISTS (SELECT 1 FROM ' . CoursesSchema::materialsTable() . ' m WHERE m.lesson_id = l.id)'
            . ' AND NOT EXISTS (SELECT 1 FROM ' . CoursesSchema::lessonTasksTable() . ' t WHERE t.lesson_id = l.id)'
            . ' AND NOT EXISTS (SELECT 1 FROM ' . CoursesSchema::qaEntriesTable() . ' q WHERE q.lesson_id = l.id)'
            . ' AND NOT EXISTS (SELECT 1 FROM ' . CoursesSchema::progressTable() . ' lp WHERE lp.lesson_id = l.id)'
            . ' LIMIT 1',
            $lessonId,
            $courseId
        ));

        if ($this->database->last_error !== '') {
            return $this->databaseError('am_toolkit_course_admin_delete_failed');
        }

        if ($safe === null) {
            return $this->unsafeDelete();
        }

        $this->begin();
        $deletedAssignments = $this->database->query($this->database->prepare(
            "DELETE pl FROM {$assignments} pl INNER JOIN {$programs} p ON p.id = pl.program_version_id"
            . " WHERE pl.lesson_id = %d AND p.course_id = %d AND p.status = 'draft'",
            $lessonId,
            $courseId
        ));
        $deletedLesson = $this->database->delete($lessons, ['id' => $lessonId, 'course_id' => $courseId], ['%d', '%d']);

        if ($deletedAssignments === false || $deletedLesson !== 1) {
            return $this->rollbackError('am_toolkit_course_admin_delete_failed');
        }

        $this->commit();
        return true;
    }

    private function deleteDraftMaterial(int $materialId, int $courseId): bool|\WP_Error
    {
        $materials = CoursesSchema::materialsTable();
        $lessons = CoursesSchema::lessonsTable();
        $safe = $this->database->get_var($this->database->prepare(
            "SELECT m.id FROM {$materials} m INNER JOIN {$lessons} l ON l.id = m.lesson_id"
            . " WHERE m.id = %d AND l.course_id = %d AND m.status = 'draft'"
            . ' AND m.created_at = m.updated_at LIMIT 1',
            $materialId,
            $courseId
        ));

        if ($this->database->last_error !== '') {
            return $this->databaseError('am_toolkit_course_admin_delete_failed');
        }

        if ($safe === null) {
            return $this->unsafeDelete();
        }

        return $this->database->delete($materials, ['id' => $materialId], ['%d']) === 1
            ? true
            : $this->databaseError('am_toolkit_course_admin_delete_failed');
    }

    private function courseHasDeletionDependencies(int $courseId): bool
    {
        $checks = [
            ['SELECT COUNT(*) FROM ' . CoursesSchema::programVersionsTable() . " WHERE course_id = %d AND status = 'published'", $courseId],
            ['SELECT COUNT(*) FROM ' . CoursesSchema::sectionsTable() . ' s INNER JOIN ' . CoursesSchema::programVersionsTable() . ' p ON p.id = s.program_version_id WHERE p.course_id = %d', $courseId],
            ['SELECT COUNT(*) FROM ' . CoursesSchema::lessonsTable() . ' WHERE course_id = %d', $courseId],
            ['SELECT COUNT(*) FROM ' . CoursesSchema::productMappingsTable() . ' WHERE course_id = %d', $courseId],
            ['SELECT COUNT(*) FROM ' . AccessSchema::grantsTable() . " WHERE resource_type = 'course' AND resource_id = %d", $courseId],
            ['SELECT COUNT(*) FROM ' . CoursesSchema::progressTable() . ' WHERE course_id = %d', $courseId],
            ['SELECT COUNT(*) FROM ' . CoursesSchema::completionsTable() . ' WHERE course_id = %d', $courseId],
            ['SELECT COUNT(*) FROM ' . CoursesSchema::meetingsTable() . ' WHERE course_id = %d', $courseId],
            ['SELECT COUNT(*) FROM ' . CoursesSchema::qaEntriesTable() . ' WHERE course_id = %d', $courseId],
        ];

        foreach ($checks as [$sql, $id]) {
            $count = $this->database->get_var($this->database->prepare($sql, $id));
            if ($this->database->last_error !== '' || (int) $count > 0) {
                return true;
            }
        }

        return false;
    }

    private function unsafeDelete(): \WP_Error
    {
        return new \WP_Error(
            'am_toolkit_course_draft_delete_blocked',
            __('Tego elementu nie można usunąć trwale. Został zmieniony, opublikowany albo ma powiązaną historię. Użyj archiwizacji.', 'am-toolkit')
        );
    }

    private function publishDraft(int $courseId, string $now): bool|\WP_Error
    {
        $programId = $this->draftProgramId($courseId);

        if (is_wp_error($programId)) {
            return $programId;
        }

        $programs = CoursesSchema::programVersionsTable();
        $assignments = CoursesSchema::programLessonsTable();
        $snapshot = $this->database->get_results(
            $this->database->prepare(
                "SELECT lesson_id, section_id, position, is_required
                FROM {$assignments} WHERE program_version_id = %d
                ORDER BY section_id ASC, position ASC, lesson_id ASC",
                $programId
            ),
            ARRAY_A
        );
        $json = wp_json_encode($snapshot ?: []);

        if ($json === false) {
            return new \WP_Error('am_toolkit_course_publish_failed', __('Nie udało się zbudować wersji programu.', 'am-toolkit'));
        }

        if ($this->database->update(
            $programs,
            ['status' => PublicationStatus::PUBLISHED, 'content_hash' => hash('sha256', $json), 'published_at' => $now],
            ['id' => $programId, 'status' => PublicationStatus::DRAFT]
        ) === false) {
            return $this->databaseError('am_toolkit_course_publish_failed');
        }

        if ($this->database->update(
            CoursesSchema::coursesTable(),
            [
                'status' => PublicationStatus::PUBLISHED,
                'current_program_version_id' => $programId,
                'updated_at' => $now,
                'archived_at' => null,
            ],
            ['id' => $courseId]
        ) === false) {
            return $this->databaseError('am_toolkit_course_publish_failed');
        }

        return $this->cloneProgramAsDraft($courseId, $programId, $now);
    }

    private function cloneProgramAsDraft(int $courseId, int $sourceProgramId, string $now): bool|\WP_Error
    {
        $programs = CoursesSchema::programVersionsTable();
        $sections = CoursesSchema::sectionsTable();
        $assignments = CoursesSchema::programLessonsTable();
        $sourceVersion = (int) $this->database->get_var(
            $this->database->prepare("SELECT version_number FROM {$programs} WHERE id = %d", $sourceProgramId)
        );
        $newProgramId = $this->createProgram(
            $courseId,
            $sourceVersion + 1,
            PublicationStatus::DRAFT,
            null,
            hash('sha256', '[]')
        );

        if (is_wp_error($newProgramId)) {
            return $newProgramId;
        }

        $sectionRows = $this->database->get_results(
            $this->database->prepare("SELECT * FROM {$sections} WHERE program_version_id = %d ORDER BY id ASC", $sourceProgramId),
            ARRAY_A
        );
        $sectionMap = [];

        foreach ($sectionRows ?: [] as $section) {
            $inserted = $this->database->insert(
                $sections,
                [
                    'public_id' => wp_generate_uuid4(),
                    'program_version_id' => $newProgramId,
                    'title' => $section['title'],
                    'description' => $section['description'],
                    'position' => $section['position'],
                    'status' => $section['status'] === PublicationStatus::ARCHIVED ? PublicationStatus::ARCHIVED : PublicationStatus::DRAFT,
                    'created_at' => $now,
                    'updated_at' => $now,
                    'archived_at' => $section['status'] === PublicationStatus::ARCHIVED ? $section['archived_at'] : null,
                ]
            );

            if ($inserted !== 1) {
                return $this->databaseError('am_toolkit_course_revision_failed');
            }

            $sectionMap[(int) $section['id']] = (int) $this->database->insert_id;
        }

        $assignmentRows = $this->database->get_results(
            $this->database->prepare("SELECT * FROM {$assignments} WHERE program_version_id = %d ORDER BY id ASC", $sourceProgramId),
            ARRAY_A
        );

        foreach ($assignmentRows ?: [] as $assignment) {
            $oldSectionId = (int) ($assignment['section_id'] ?? 0);
            $inserted = $this->database->insert(
                $assignments,
                [
                    'program_version_id' => $newProgramId,
                    'lesson_id' => (int) $assignment['lesson_id'],
                    'section_id' => $oldSectionId > 0 ? ($sectionMap[$oldSectionId] ?? null) : null,
                    'position' => (int) $assignment['position'],
                    'is_required' => (int) $assignment['is_required'],
                ]
            );

            if ($inserted !== 1) {
                return $this->databaseError('am_toolkit_course_revision_failed');
            }
        }

        return true;
    }

    private function createProgram(
        int $courseId,
        int $version,
        string $status,
        ?string $publishedAt,
        string $contentHash
    ): int|\WP_Error {
        $result = $this->database->insert(
            CoursesSchema::programVersionsTable(),
            [
                'public_id' => wp_generate_uuid4(),
                'course_id' => $courseId,
                'version_number' => $version,
                'status' => $status,
                'content_hash' => $contentHash,
                'published_at' => $publishedAt,
                'created_at' => current_time('mysql', true),
            ]
        );

        return $result === 1
            ? (int) $this->database->insert_id
            : $this->databaseError('am_toolkit_course_admin_write_failed');
    }

    private function workspaceProgramId(int $courseId): int|\WP_Error
    {
        $draft = $this->database->get_var(
            $this->database->prepare(
                'SELECT id FROM ' . CoursesSchema::programVersionsTable()
                . " WHERE course_id = %d AND status = 'draft' ORDER BY version_number DESC LIMIT 1",
                $courseId
            )
        );

        if ($draft !== null) {
            return (int) $draft;
        }

        $current = $this->database->get_var(
            $this->database->prepare(
                'SELECT current_program_version_id FROM ' . CoursesSchema::coursesTable() . ' WHERE id = %d',
                $courseId
            )
        );

        return $current !== null && (int) $current > 0
            ? (int) $current
            : $this->notFound();
    }

    private function draftProgramId(int $courseId): int|\WP_Error
    {
        $programs = CoursesSchema::programVersionsTable();
        $programId = $this->database->get_var(
            $this->database->prepare(
                "SELECT id FROM {$programs}
                WHERE course_id = %d AND status = 'draft'
                ORDER BY version_number DESC LIMIT 1",
                $courseId
            )
        );

        return $programId !== null
            ? (int) $programId
            : new \WP_Error(
                'am_toolkit_course_draft_required',
                __('Kurs nie ma wersji roboczej programu.', 'am-toolkit')
            );
    }

    private function courseExists(int $courseId): bool
    {
        return (bool) $this->database->get_var(
            $this->database->prepare('SELECT id FROM ' . CoursesSchema::coursesTable() . ' WHERE id = %d', $courseId)
        );
    }

    private function lessonExists(int $lessonId): bool
    {
        return (bool) $this->database->get_var(
            $this->database->prepare('SELECT id FROM ' . CoursesSchema::lessonsTable() . ' WHERE id = %d', $lessonId)
        );
    }

    private function materialBelongsToLesson(int $materialId, int $lessonId): bool
    {
        return (bool) $this->database->get_var(
            $this->database->prepare(
                'SELECT id FROM ' . CoursesSchema::materialsTable() . ' WHERE id = %d AND lesson_id = %d',
                $materialId,
                $lessonId
            )
        );
    }

    private function sectionBelongsToProgram(int $sectionId, int $programId): bool
    {
        return (bool) $this->database->get_var(
            $this->database->prepare(
                'SELECT id FROM ' . CoursesSchema::sectionsTable() . ' WHERE id = %d AND program_version_id = %d',
                $sectionId,
                $programId
            )
        );
    }

    private function reorderSections(int $programId, int $sectionId, int $targetPosition): bool
    {
        $sections = CoursesSchema::sectionsTable();
        $sectionIds = array_map(
            'intval',
            $this->database->get_col(
                $this->database->prepare(
                    "SELECT id FROM {$sections} WHERE program_version_id = %d ORDER BY position ASC, id ASC",
                    $programId
                )
            )
        );

        if ($this->database->last_error !== '') {
            return false;
        }

        $sectionIds = array_values(array_filter(
            $sectionIds,
            static fn (int $id): bool => $id !== $sectionId
        ));
        array_splice($sectionIds, min($targetPosition, count($sectionIds)), 0, [$sectionId]);

        $temporaryBase = ((int) $this->database->get_var(
            $this->database->prepare(
                "SELECT COALESCE(MAX(position), 0) FROM {$sections} WHERE program_version_id = %d",
                $programId
            )
        )) + count($sectionIds) + 1000;

        foreach ($sectionIds as $offset => $id) {
            if ($this->database->update(
                $sections,
                ['position' => $temporaryBase + $offset],
                ['id' => $id],
                ['%d'],
                ['%d']
            ) === false) {
                return false;
            }
        }

        foreach ($sectionIds as $position => $id) {
            if ($this->database->update(
                $sections,
                ['position' => $position],
                ['id' => $id],
                ['%d'],
                ['%d']
            ) === false) {
                return false;
            }
        }

        return true;
    }

    private function setCourseStatus(int $courseId, string $status, string $updatedAt, ?string $archivedAt): int|false
    {
        return $this->database->update(
            CoursesSchema::coursesTable(),
            ['status' => $status, 'updated_at' => $updatedAt, 'archived_at' => $archivedAt],
            ['id' => $courseId]
        );
    }

    /** @return list<array<string, mixed>>|\WP_Error */
    private function rowsOrError(?array $rows, string $code): array|\WP_Error
    {
        if ($this->database->last_error !== '') {
            return $this->databaseError($code);
        }

        return is_array($rows) ? array_values($rows) : [];
    }

    private function notFound(): \WP_Error
    {
        return new \WP_Error(
            'am_toolkit_course_admin_not_found',
            __('Nie znaleziono wskazanego zasobu kursu.', 'am-toolkit')
        );
    }

    private function databaseError(string $code): \WP_Error
    {
        return new \WP_Error(
            $code,
            __('Operacja panelu kursów nie powiodła się.', 'am-toolkit'),
            ['database_error' => $this->database->last_error]
        );
    }

    private function begin(): void
    {
        $this->database->query('START TRANSACTION');
    }

    private function commit(): void
    {
        $this->database->query('COMMIT');
    }

    private function rollback(): void
    {
        $this->database->query('ROLLBACK');
    }

    private function rollbackError(string $code): \WP_Error
    {
        $error = $this->databaseError($code);
        $this->rollback();

        return $error;
    }
}
