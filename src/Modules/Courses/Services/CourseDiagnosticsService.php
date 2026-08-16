<?php

namespace AMToolkit\Modules\Courses\Services;

use AMToolkit\Core\Diagnostics\ActivityEventQuery;
use AMToolkit\Core\Diagnostics\DomainEvent;
use AMToolkit\Core\Diagnostics\RequestId;
use AMToolkit\Core\Diagnostics\TechnicalLogger;
use AMToolkit\Core\Diagnostics\WpTechnicalLogger;
use AMToolkit\Modules\Access\ActivityEventStore;
use AMToolkit\Modules\Courses\Contracts\CourseDiagnosticsStore;
use AMToolkit\Modules\Courses\Contracts\CourseProgressDiagnostics;

defined('ABSPATH') || exit;

final class CourseDiagnosticsService
{
    public function __construct(
        private CourseDiagnosticsStore $store,
        private CourseProgressDiagnostics $progress,
        private ActivityEventStore $events,
        private ?TechnicalLogger $logger = null
    ) {
        $this->logger ??= new WpTechnicalLogger();
    }

    /** @return array<string, mixed>|\WP_Error */
    public function health(): array|\WP_Error
    {
        return $this->store->schemaHealth();
    }

    /** @return array<string, mixed>|\WP_Error */
    public function inspect(int $userId, int $courseId): array|\WP_Error
    {
        $snapshot = $this->store->snapshot($userId, $courseId);

        if (is_wp_error($snapshot)) {
            return $snapshot;
        }

        $course = (array) ($snapshot['course'] ?? []);
        $lessons = array_values((array) ($snapshot['lessons'] ?? []));
        $activeAccess = array_filter(
            (array) ($snapshot['grants'] ?? []),
            static fn (array $grant): bool => !empty($grant['is_active'])
        ) !== [];
        $lessonStates = [];
        $issues = [];

        if (empty($snapshot['user_exists'])) {
            $issues[] = $this->issue(
                'user_missing',
                'error',
                __('Nie istnieje wskazany użytkownik.', 'am-toolkit')
            );
        }

        $programPublished = is_array($snapshot['program'] ?? null)
            && (string) ($snapshot['program']['status'] ?? '') === 'published';

        if (!$programPublished) {
            $issues[] = $this->issue(
                'published_program_missing',
                'error',
                __('Kurs nie ma opublikowanej wersji programu.', 'am-toolkit')
            );
        }

        if (!$activeAccess) {
            $issues[] = $this->issue(
                'active_grant_missing',
                'warning',
                __('Użytkownik nie ma obecnie aktywnego dostępu do kursu.', 'am-toolkit')
            );
        }

        foreach ($lessons as $lesson) {
            $lessonId = (int) ($lesson['id'] ?? 0);
            $currentVersion = (int) ($lesson['content_version'] ?? 0);
            $progressVersion = (int) ($lesson['progress_content_version'] ?? 0);

            if ((string) ($lesson['progress_status'] ?? '') !== '' && $progressVersion !== $currentVersion) {
                $issues[] = $this->issue(
                    'stale_lesson_progress',
                    'warning',
                    sprintf(
                        /* translators: %s: lesson title */
                        __('Postęp lekcji „%s” dotyczy nieaktualnej wersji treści.', 'am-toolkit'),
                        (string) ($lesson['title'] ?? '')
                    ),
                    true,
                    $lessonId
                );
            }

            if (!$activeAccess || empty($snapshot['user_exists'])) {
                continue;
            }

            $state = $this->progress->lessonState(
                $userId,
                (string) ($course['public_id'] ?? ''),
                (string) ($lesson['public_id'] ?? '')
            );

            if (is_wp_error($state)) {
                $lessonStates[$lessonId] = ['error_code' => $state->get_error_code()];
                $issues[] = $this->issue(
                    'lesson_state_unavailable',
                    'error',
                    sprintf(
                        /* translators: %s: lesson title */
                        __('Nie udało się przeliczyć stanu lekcji „%s”.', 'am-toolkit'),
                        (string) ($lesson['title'] ?? '')
                    ),
                    false,
                    $lessonId
                );
                continue;
            }

            $lessonStates[$lessonId] = $this->safeLessonState($state);

            if (
                (int) ($state['lesson_progress_percent'] ?? 0) >= 100
                && (
                    (string) ($lesson['progress_status'] ?? '') !== 'completed'
                    || $progressVersion !== $currentVersion
                )
            ) {
                $issues[] = $this->issue(
                    'lesson_aggregate_outdated',
                    'warning',
                    sprintf(
                        /* translators: %s: lesson title */
                        __('Źródła wskazują ukończenie lekcji „%s”, ale zapisany agregat jest nieaktualny.', 'am-toolkit'),
                        (string) ($lesson['title'] ?? '')
                    ),
                    true,
                    $lessonId
                );
            }
        }

        $requiredIds = array_values(array_map(
            static fn (array $lesson): int => (int) ($lesson['id'] ?? 0),
            array_filter($lessons, static fn (array $lesson): bool => !empty($lesson['is_required']))
        ));
        $completedRequiredIds = array_values(array_map(
            static fn (array $lesson): int => (int) ($lesson['id'] ?? 0),
            array_filter($lessons, static fn (array $lesson): bool =>
                !empty($lesson['is_required'])
                && (string) ($lesson['progress_status'] ?? '') === 'completed'
                && (int) ($lesson['progress_content_version'] ?? 0) === (int) ($lesson['content_version'] ?? 0)
            )
        ));
        $completion = is_array($snapshot['completion'] ?? null) ? $snapshot['completion'] : null;
        $allRequiredComplete = $requiredIds !== []
            && count(array_intersect($requiredIds, $completedRequiredIds)) === count($requiredIds);

        if ($requiredIds === []) {
            $issues[] = $this->issue(
                'required_lessons_missing',
                'warning',
                __('Opublikowany program nie zawiera wymaganych lekcji.', 'am-toolkit')
            );
        } elseif ($allRequiredComplete && $completion === null) {
            $issues[] = $this->issue(
                'course_completion_missing',
                'warning',
                __('Wszystkie wymagane lekcje są ukończone, ale brakuje ukończenia kursu.', 'am-toolkit'),
                $activeAccess
            );
        } elseif (!$allRequiredComplete && $completion !== null) {
            $issues[] = $this->issue(
                'course_completion_inconsistent',
                'error',
                __('Kurs jest zapisany jako ukończony mimo nieukończonych wymaganych lekcji.', 'am-toolkit')
            );
        }

        if ($completion !== null) {
            $storedRequiredIds = array_values(array_map('intval', (array) ($completion['required_lesson_ids'] ?? [])));
            sort($storedRequiredIds);
            $expectedRequiredIds = $requiredIds;
            sort($expectedRequiredIds);
            $expectedHash = hash('sha256', implode(',', $expectedRequiredIds));

            if (
                empty($completion['required_lesson_ids_valid'])
                || $storedRequiredIds !== $expectedRequiredIds
                || !hash_equals($expectedHash, (string) ($completion['requirements_hash'] ?? ''))
            ) {
                $issues[] = $this->issue(
                    'completion_snapshot_inconsistent',
                    'error',
                    __('Migawka wymagań ukończenia kursu nie odpowiada opublikowanemu programowi.', 'am-toolkit')
                );
            }
        }

        $events = $this->courseEvents($userId, $courseId, $lessons);

        if (is_wp_error($events)) {
            return $events;
        }

        $repairAvailable = !empty($snapshot['user_exists'])
            && $activeAccess
            && $programPublished
            && $lessons !== []
            && array_filter(
                $lessonStates,
                static fn (array $state): bool => isset($state['error_code'])
            ) === [];

        return [
            'checked_at' => current_time('mysql', true),
            'snapshot' => $snapshot,
            'active_access' => $activeAccess,
            'lesson_states' => $lessonStates,
            'aggregate' => [
                'required_lessons' => count($requiredIds),
                'completed_required_lessons' => count(array_intersect($requiredIds, $completedRequiredIds)),
                'expected_progress_percent' => $requiredIds === []
                    ? 0
                    : (int) floor((count(array_intersect($requiredIds, $completedRequiredIds)) / count($requiredIds)) * 100),
                'completion_recorded' => $completion !== null,
            ],
            'events' => $events,
            'issues' => $issues,
            'repair_preview' => [
                'available' => $repairAvailable,
                'would_write' => array_filter($issues, static fn (array $issue): bool => !empty($issue['repairable'])) !== [],
                'lesson_count' => count($lessons),
                'actions' => array_map(static fn (array $lesson): array => [
                    'action' => 'recalculate_lesson',
                    'lesson_id' => (int) ($lesson['id'] ?? 0),
                    'lesson_title' => (string) ($lesson['title'] ?? ''),
                ], $lessons),
            ],
        ];
    }

    /** @return array<string, mixed>|\WP_Error */
    public function repair(int $userId, int $courseId, ?string $requestId = null): array|\WP_Error
    {
        $requestId = RequestId::normalize($requestId);
        $before = $this->inspect($userId, $courseId);

        if (is_wp_error($before)) {
            return $before;
        }

        if (empty($before['repair_preview']['available'])) {
            return new \WP_Error(
                'am_toolkit_course_repair_unavailable',
                __('Nie można bezpiecznie przeliczyć tego kursu. Najpierw usuń błędy blokujące.', 'am-toolkit'),
                ['request_id' => $requestId]
            );
        }

        $snapshot = (array) $before['snapshot'];
        $course = (array) ($snapshot['course'] ?? []);
        $attempted = 0;

        foreach ((array) ($snapshot['lessons'] ?? []) as $lesson) {
            $attempted++;
            $result = $this->progress->rebuildLessonProgress(
                $userId,
                (string) ($course['public_id'] ?? ''),
                (string) ($lesson['public_id'] ?? ''),
                $requestId
            );

            if (is_wp_error($result)) {
                $this->recordRepairFailure($userId, $courseId, $requestId, $result->get_error_code(), $attempted);

                return new \WP_Error(
                    'am_toolkit_course_repair_failed',
                    __('Przeliczenie nie zostało dokończone. Operację można bezpiecznie ponowić.', 'am-toolkit'),
                    [
                        'request_id' => $requestId,
                        'error_code' => $result->get_error_code(),
                        'lesson_id' => (int) ($lesson['id'] ?? 0),
                    ]
                );
            }
        }

        $after = $this->inspect($userId, $courseId);

        if (is_wp_error($after)) {
            $this->recordRepairFailure($userId, $courseId, $requestId, $after->get_error_code(), $attempted);
            return $after;
        }

        $changes = $this->changes($before, $after);
        $audit = $this->events->record(DomainEvent::create(
            'course.progress.recalculated.' . $userId . '.' . $courseId . '.' . $requestId,
            'course.progress.recalculated',
            $userId,
            get_current_user_id(),
            'course',
            $courseId,
            [
                'attempted_lessons' => $attempted,
                'changed_lessons' => $changes['lessons'],
                'completion_changed' => $changes['completion'],
                'before_issues' => count((array) ($before['issues'] ?? [])),
                'after_issues' => count((array) ($after['issues'] ?? [])),
            ],
            current_time('mysql', true),
            $requestId
        ));

        if (is_wp_error($audit)) {
            $this->logger->error('Nie udało się zapisać audytu przeliczenia postępu kursu.', [
                'request_id' => $requestId,
                'error_code' => $audit->get_error_code(),
                'operation' => 'course_progress_repair_audit',
                'object_type' => 'course',
                'object_id' => $courseId,
            ]);

            return new \WP_Error(
                'am_toolkit_course_repair_audit_failed',
                __('Postęp przeliczono, ale zapis audytu nie powiódł się. Operację można bezpiecznie ponowić.', 'am-toolkit'),
                ['request_id' => $requestId]
            );
        }

        return [
            'request_id' => $requestId,
            'attempted_lessons' => $attempted,
            'changed_lessons' => $changes['lessons'],
            'completion_changed' => $changes['completion'],
            'changed_aggregates' => $changes['lessons'] + ($changes['completion'] ? 1 : 0),
            'diagnostics' => $after,
        ];
    }

    /** @return string|\WP_Error */
    public function export(int $userId, int $courseId): string|\WP_Error
    {
        $diagnostics = $this->inspect($userId, $courseId);

        if (is_wp_error($diagnostics)) {
            return $diagnostics;
        }

        $health = $this->health();

        if (is_wp_error($health)) {
            return $health;
        }

        $snapshot = (array) ($diagnostics['snapshot'] ?? []);
        $course = (array) ($snapshot['course'] ?? []);
        $safe = [
            'export_version' => 1,
            'generated_at' => current_time('mysql', true),
            'plugin_version' => defined('AM_TOOLKIT_VERSION') ? AM_TOOLKIT_VERSION : 'unknown',
            'user_ref' => $this->pseudonymize($userId),
            'course' => [
                'id' => (int) ($course['id'] ?? 0),
                'public_id' => (string) ($course['public_id'] ?? ''),
                'status' => (string) ($course['status'] ?? ''),
                'published_program_version_id' => (int) ($course['published_program_version_id'] ?? 0),
            ],
            'health' => $health,
            'active_access' => !empty($diagnostics['active_access']),
            'grant_states' => array_map(static fn (array $grant): array => [
                'status' => (string) ($grant['status'] ?? ''),
                'source_type' => sanitize_key((string) ($grant['source_type'] ?? '')),
                'starts_at' => (string) ($grant['starts_at'] ?? ''),
                'expires_at' => (string) ($grant['expires_at'] ?? ''),
                'is_active' => !empty($grant['is_active']),
            ], (array) ($snapshot['grants'] ?? [])),
            'program' => is_array($snapshot['program'] ?? null) ? [
                'id' => (int) ($snapshot['program']['id'] ?? 0),
                'version_number' => (int) ($snapshot['program']['version_number'] ?? 0),
                'status' => (string) ($snapshot['program']['status'] ?? ''),
                'published_at' => (string) ($snapshot['program']['published_at'] ?? ''),
                'created_at' => (string) ($snapshot['program']['created_at'] ?? ''),
            ] : null,
            'lessons' => array_map(static fn (array $lesson): array => [
                'id' => (int) ($lesson['id'] ?? 0),
                'public_id' => (string) ($lesson['public_id'] ?? ''),
                'content_version' => (int) ($lesson['content_version'] ?? 0),
                'is_required' => !empty($lesson['is_required']),
                'progress_status' => (string) ($lesson['progress_status'] ?? ''),
                'progress_content_version' => (int) ($lesson['progress_content_version'] ?? 0),
                'request_id' => (string) ($lesson['request_id'] ?? ''),
                'completed_at' => (string) ($lesson['completed_at'] ?? ''),
            ], (array) ($snapshot['lessons'] ?? [])),
            'completion' => is_array($snapshot['completion'] ?? null) ? [
                'id' => (int) ($snapshot['completion']['id'] ?? 0),
                'required_lesson_ids' => array_values(array_map('intval', (array) ($snapshot['completion']['required_lesson_ids'] ?? []))),
                'required_lesson_ids_valid' => !empty($snapshot['completion']['required_lesson_ids_valid']),
                'requirements_hash' => (string) ($snapshot['completion']['requirements_hash'] ?? ''),
                'request_id' => (string) ($snapshot['completion']['request_id'] ?? ''),
                'completed_at' => (string) ($snapshot['completion']['completed_at'] ?? ''),
            ] : null,
            'aggregate' => $diagnostics['aggregate'] ?? [],
            'lesson_states' => $diagnostics['lesson_states'] ?? [],
            'issues' => array_map(static fn (array $issue): array => [
                'code' => (string) ($issue['code'] ?? ''),
                'severity' => (string) ($issue['severity'] ?? ''),
                'repairable' => !empty($issue['repairable']),
                'lesson_id' => (int) ($issue['lesson_id'] ?? 0),
            ], (array) ($diagnostics['issues'] ?? [])),
            'events' => $diagnostics['events'] ?? [],
        ];

        $encoded = wp_json_encode($safe, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        return $encoded === false
            ? new \WP_Error(
                'am_toolkit_course_diagnostic_export_failed',
                __('Nie udało się przygotować eksportu diagnostyki kursu.', 'am-toolkit')
            )
            : $encoded;
    }

    /** @param list<array<string, mixed>> $lessons @return list<array<string, mixed>>|\WP_Error */
    private function courseEvents(int $userId, int $courseId, array $lessons): array|\WP_Error
    {
        $events = $this->events->find(new ActivityEventQuery(userId: $userId, limit: 50));

        if (is_wp_error($events)) {
            return $events;
        }

        $lessonIds = array_map(static fn (array $lesson): int => (int) ($lesson['id'] ?? 0), $lessons);
        $safe = [];

        foreach ($events as $event) {
            $payload = is_array($event['payload'] ?? null) ? $event['payload'] : [];
            $objectType = (string) ($event['object_type'] ?? '');
            $objectId = (int) ($event['object_id'] ?? 0);
            $matches = ($objectType === 'course' && $objectId === $courseId)
                || ($objectType === 'lesson' && in_array($objectId, $lessonIds, true))
                || (int) ($payload['course_id'] ?? 0) === $courseId;

            if (!$matches) {
                continue;
            }

            $safe[] = [
                'event_type' => (string) ($event['event_type'] ?? ''),
                'request_id' => (string) ($event['request_id'] ?? ''),
                'object_type' => $objectType,
                'object_id' => $objectId,
                'occurred_at' => (string) ($event['occurred_at'] ?? ''),
            ];
        }

        return $safe;
    }

    /** @param array<string, mixed> $state @return array<string, mixed> */
    private function safeLessonState(array $state): array
    {
        return [
            'status' => (string) ($state['status'] ?? ''),
            'lesson_completed' => !empty($state['lesson_completed']),
            'watched_percent' => (float) ($state['watched_percent'] ?? 0),
            'lesson_progress_percent' => (int) ($state['lesson_progress_percent'] ?? 0),
            'course_completed' => !empty($state['course_completed']),
            'course_progress_percent' => (int) ($state['course_progress_percent'] ?? 0),
        ];
    }

    /** @return array<string, mixed> */
    private function issue(
        string $code,
        string $severity,
        string $message,
        bool $repairable = false,
        int $lessonId = 0
    ): array {
        return [
            'code' => $code,
            'severity' => $severity,
            'message' => $message,
            'repairable' => $repairable,
            'lesson_id' => $lessonId,
        ];
    }

    /** @param array<string, mixed> $before @param array<string, mixed> $after */
    /** @return array{lessons: int, completion: bool} */
    private function changes(array $before, array $after): array
    {
        $beforeLessons = [];

        foreach ((array) ($before['snapshot']['lessons'] ?? []) as $lesson) {
            $beforeLessons[(int) ($lesson['id'] ?? 0)] = [
                (string) ($lesson['progress_status'] ?? ''),
                (int) ($lesson['progress_content_version'] ?? 0),
                (string) ($lesson['completed_at'] ?? ''),
            ];
        }

        $changed = 0;

        foreach ((array) ($after['snapshot']['lessons'] ?? []) as $lesson) {
            $lessonId = (int) ($lesson['id'] ?? 0);
            $state = [
                (string) ($lesson['progress_status'] ?? ''),
                (int) ($lesson['progress_content_version'] ?? 0),
                (string) ($lesson['completed_at'] ?? ''),
            ];

            if (($beforeLessons[$lessonId] ?? null) !== $state) {
                $changed++;
            }
        }

        return [
            'lessons' => $changed,
            'completion' => !empty($before['aggregate']['completion_recorded'])
                !== !empty($after['aggregate']['completion_recorded']),
        ];
    }

    private function recordRepairFailure(
        int $userId,
        int $courseId,
        string $requestId,
        string $errorCode,
        int $attempted
    ): void {
        $event = DomainEvent::create(
            'course.progress.recalculation_failed.' . $userId . '.' . $courseId . '.' . $requestId,
            'course.progress.recalculation_failed',
            $userId,
            get_current_user_id(),
            'course',
            $courseId,
            ['error_code' => $errorCode, 'attempted_lessons' => $attempted],
            current_time('mysql', true),
            $requestId
        );
        $recorded = $this->events->record($event);

        $this->logger->error('Przeliczenie postępu kursu nie zostało dokończone.', [
            'request_id' => $requestId,
            'error_code' => $errorCode,
            'event_type' => 'course.progress.recalculation_failed',
            'operation' => 'course_progress_repair',
            'object_type' => 'course',
            'object_id' => $courseId,
        ]);

        if (is_wp_error($recorded)) {
            $this->logger->error('Nie udało się zapisać audytu błędu przeliczenia kursu.', [
                'request_id' => $requestId,
                'error_code' => $recorded->get_error_code(),
                'operation' => 'course_progress_repair_failure_audit',
                'object_type' => 'course',
                'object_id' => $courseId,
            ]);
        }
    }

    private function pseudonymize(int $userId): string
    {
        return $userId > 0
            ? substr(hash_hmac('sha256', (string) $userId, wp_salt('auth')), 0, 16)
            : 'system';
    }
}
