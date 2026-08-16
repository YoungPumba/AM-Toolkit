<?php

namespace AMToolkit\Modules\Courses;

use AMToolkit\Modules\Courses\Contracts\CourseMeetingStore;
use AMToolkit\Modules\Courses\Contracts\CourseResourceArchiveStore;

defined('ABSPATH') || exit;

final class WpdbCourseMeetingStore implements CourseMeetingStore, CourseResourceArchiveStore
{
    private \wpdb $database;

    public function __construct(?\wpdb $database = null)
    {
        global $wpdb;
        $this->database = $database ?? $wpdb;
    }

    public function courseSettings(int $courseId): array|null|\WP_Error
    {
        $row = $this->database->get_row(
            $this->database->prepare(
                'SELECT id, telegram_reference FROM ' . CoursesSchema::coursesTable() . ' WHERE id = %d LIMIT 1',
                $courseId
            ),
            ARRAY_A
        );

        if ($this->database->last_error !== '') {
            return $this->databaseError();
        }

        return is_array($row) ? $row : null;
    }

    public function meetingsForCourse(int $courseId): array|\WP_Error
    {
        $rows = $this->database->get_results(
            $this->database->prepare(
                'SELECT id, public_id, course_id, title, description, starts_at_utc, ends_at_utc,'
                . ' display_timezone, platform, location, join_reference, recording_reference, status, updated_at'
                . ' FROM ' . CoursesSchema::meetingsTable()
                . ' WHERE course_id = %d AND archived_at IS NULL ORDER BY starts_at_utc DESC, id DESC',
                $courseId
            ),
            ARRAY_A
        );

        return $this->rowsOrError($rows);
    }

    public function saveMeeting(array $meeting, int $actorId, string $requestId): int|\WP_Error
    {
        $table = CoursesSchema::meetingsTable();
        $revisions = CoursesSchema::meetingRevisionsTable();
        $meetingId = (int) ($meeting['id'] ?? 0);
        $now = current_time('mysql', true);
        $data = [
            'course_id' => (int) $meeting['course_id'],
            'title' => (string) $meeting['title'],
            'description' => (string) $meeting['description'],
            'starts_at_utc' => (string) $meeting['starts_at_utc'],
            'ends_at_utc' => (string) $meeting['ends_at_utc'],
            'display_timezone' => (string) $meeting['display_timezone'],
            'platform' => (string) $meeting['platform'],
            'location' => (string) $meeting['location'],
            'join_reference' => $meeting['join_reference'],
            'recording_reference' => $meeting['recording_reference'],
            'status' => (string) $meeting['status'],
            'updated_at' => $now,
        ];

        if ($meetingId > 0) {
            $existing = $this->database->get_var($this->database->prepare(
                "SELECT id FROM {$table} WHERE id = %d AND course_id = %d AND archived_at IS NULL",
                $meetingId,
                (int) $meeting['course_id']
            ));

            if ($this->database->last_error !== '') {
                return $this->databaseError();
            }

            if ($existing === null) {
                return new \WP_Error(
                    'am_toolkit_course_meeting_not_found',
                    __('Nie znaleziono spotkania w tym kursie.', 'am-toolkit')
                );
            }
        }

        $this->database->query('START TRANSACTION');

        if ($meetingId > 0) {
            $result = $this->database->update(
                $table,
                $data,
                ['id' => $meetingId, 'course_id' => (int) $meeting['course_id']]
            );

            if ($result === false) {
                return $this->rollbackError();
            }
        } else {
            $data += [
                'public_id' => wp_generate_uuid4(),
                'created_at' => $now,
                'archived_at' => null,
            ];

            if ($this->database->insert($table, $data) !== 1) {
                return $this->rollbackError();
            }

            $meetingId = (int) $this->database->insert_id;
        }

        $revisionNumber = ((int) $this->database->get_var(
            $this->database->prepare(
                "SELECT COALESCE(MAX(revision_number), 0) FROM {$revisions} WHERE meeting_id = %d FOR UPDATE",
                $meetingId
            )
        )) + 1;
        $snapshot = wp_json_encode($data);

        if ($snapshot === false || $this->database->insert($revisions, [
            'meeting_id' => $meetingId,
            'course_id' => (int) $meeting['course_id'],
            'revision_number' => $revisionNumber,
            'snapshot' => $snapshot,
            'actor_id' => max(0, $actorId),
            'request_id' => $requestId,
            'created_at' => $now,
        ]) !== 1) {
            return $this->rollbackError();
        }

        $this->database->query('COMMIT');
        return $meetingId;
    }

    public function saveTelegramReference(int $courseId, ?string $reference): bool|\WP_Error
    {
        $result = $this->database->update(
            CoursesSchema::coursesTable(),
            ['telegram_reference' => $reference, 'updated_at' => current_time('mysql', true)],
            ['id' => $courseId]
        );

        return $result === false ? $this->databaseError() : true;
    }

    public function archiveCourseResource(string $resourceType, int $resourceId, int $courseId): bool|\WP_Error
    {
        if ($resourceType !== 'meeting' || $resourceId <= 0 || $courseId <= 0) {
            return new \WP_Error('am_toolkit_course_meeting_invalid', __('Dane spotkania są nieprawidłowe.', 'am-toolkit'));
        }

        $now = current_time('mysql', true);
        $result = $this->database->update(
            CoursesSchema::meetingsTable(),
            ['status' => 'cancelled', 'archived_at' => $now, 'updated_at' => $now],
            ['id' => $resourceId, 'course_id' => $courseId]
        );

        if ($result === false) {
            return $this->databaseError();
        }

        return $result === 1
            ? true
            : new \WP_Error('am_toolkit_course_meeting_not_found', __('Nie znaleziono spotkania w tym kursie.', 'am-toolkit'));
    }

    public function nearestMeetings(array $courseIds, string $atUtc): array|\WP_Error
    {
        $courseIds = array_values(array_unique(array_filter(array_map('absint', $courseIds))));
        if ($courseIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($courseIds), '%d'));
        $table = CoursesSchema::meetingsTable();
        $sql = "SELECT m.* FROM {$table} m
            INNER JOIN (
                SELECT course_id, MIN(starts_at_utc) AS nearest_start
                FROM {$table}
                WHERE course_id IN ({$placeholders})
                    AND starts_at_utc >= %s
                    AND status IN ('scheduled', 'rescheduled')
                    AND archived_at IS NULL
                GROUP BY course_id
            ) nearest ON nearest.course_id = m.course_id AND nearest.nearest_start = m.starts_at_utc
            WHERE m.status IN ('scheduled', 'rescheduled') AND m.archived_at IS NULL
            ORDER BY m.id ASC";
        $rows = $this->database->get_results(
            $this->database->prepare($sql, ...[...$courseIds, $atUtc]),
            ARRAY_A
        );
        $rows = $this->rowsOrError($rows);

        if (is_wp_error($rows)) {
            return $rows;
        }

        $result = [];
        foreach ($rows as $row) {
            $courseId = (int) $row['course_id'];
            $result[$courseId] ??= $row;
        }

        return $result;
    }

    private function rowsOrError(mixed $rows): array|\WP_Error
    {
        if ($this->database->last_error !== '') {
            return $this->databaseError();
        }

        return is_array($rows) ? array_values($rows) : [];
    }

    private function rollbackError(): \WP_Error
    {
        $error = $this->databaseError();
        $this->database->query('ROLLBACK');
        return $error;
    }

    private function databaseError(): \WP_Error
    {
        return new \WP_Error(
            'am_toolkit_course_meeting_database_failed',
            __('Nie udało się zapisać lub odczytać informacji o spotkaniu.', 'am-toolkit'),
            ['database_error' => $this->database->last_error]
        );
    }
}
