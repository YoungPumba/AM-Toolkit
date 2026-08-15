<?php

declare(strict_types=1);

use AMToolkit\Modules\Access\Access;
use AMToolkit\Modules\Access\AccessSchema;
use AMToolkit\Modules\Courses\CoursesSchema;
use AMToolkit\Modules\Courses\Domain\PublicationStatus;
use AMToolkit\Modules\Courses\Services\AccessCoreCourseEntitlementGateway;
use AMToolkit\Modules\Courses\Services\CourseAccessLifecycle;
use AMToolkit\Modules\Courses\Services\CourseAdminService;
use AMToolkit\Modules\Courses\WpdbCourseAdminStore;
use AMToolkit\Modules\Courses\WpdbProductCourseMappingStore;

$mode = $argv[1] ?? '';
$wpLoad = $argv[2] ?? '';
$databaseHost = $argv[3] ?? '';
$username = $argv[4] ?? '';
$password = $argv[5] ?? '';

if (!in_array($mode, ['setup', 'cleanup'], true) || $wpLoad === '' || !is_file($wpLoad) || $databaseHost === '') {
    fwrite(STDERR, "Usage: php .build/course-hub-browser-fixture.php <setup|cleanup> <wp-load.php> <database-host:port> [username] [password]\n");
    exit(2);
}

define('DB_HOST', $databaseHost);
set_error_handler(static function (int $severity, string $message): bool {
    return $severity === E_WARNING && str_contains($message, 'Constant DB_HOST already defined');
});
require $wpLoad;
restore_error_handler();

global $wpdb;

$fixtureOption = 'amt_via41_browser_fixture';
$cleanup = static function () use ($wpdb, $fixtureOption): void {
    $fixture = get_option($fixtureOption, []);

    if (!is_array($fixture)) {
        $fixture = [];
    }

    foreach (array_map('intval', $fixture['course_ids'] ?? []) as $courseId) {
        $programIds = array_map('intval', $wpdb->get_col($wpdb->prepare(
            'SELECT id FROM ' . CoursesSchema::programVersionsTable() . ' WHERE course_id = %d',
            $courseId
        )));
        $lessonIds = array_map('intval', $wpdb->get_col($wpdb->prepare(
            'SELECT id FROM ' . CoursesSchema::lessonsTable() . ' WHERE course_id = %d',
            $courseId
        )));

        $wpdb->delete(AccessSchema::eventsTable(), ['object_type' => 'course', 'object_id' => $courseId]);
        $wpdb->delete(AccessSchema::grantsTable(), ['resource_type' => 'course', 'resource_id' => $courseId]);
        $wpdb->delete(CoursesSchema::completionsTable(), ['course_id' => $courseId]);
        $wpdb->delete(CoursesSchema::progressTable(), ['course_id' => $courseId]);
        $wpdb->delete(CoursesSchema::productMappingsTable(), ['course_id' => $courseId]);

        foreach ($lessonIds as $lessonId) {
            $wpdb->delete(CoursesSchema::materialsTable(), ['lesson_id' => $lessonId]);
        }

        foreach ($programIds as $programId) {
            $wpdb->delete(CoursesSchema::programLessonsTable(), ['program_version_id' => $programId]);
            $wpdb->delete(CoursesSchema::sectionsTable(), ['program_version_id' => $programId]);
        }

        $wpdb->delete(CoursesSchema::lessonsTable(), ['course_id' => $courseId]);
        $wpdb->delete(CoursesSchema::programVersionsTable(), ['course_id' => $courseId]);
        $wpdb->delete(CoursesSchema::coursesTable(), ['id' => $courseId]);
    }

    $userId = (int) ($fixture['user_id'] ?? 0);

    if ($userId > 0) {
        require_once ABSPATH . 'wp-admin/includes/user.php';
        wp_delete_user($userId);
    }

    if (array_key_exists('previous_flags', $fixture)) {
        update_option('am_toolkit_feature_flags', $fixture['previous_flags'], false);
    }

    delete_option($fixtureOption);
};

if ($mode === 'cleanup') {
    $cleanup();
    echo "VIA-41 browser fixture cleanup: OK\n";
    exit(0);
}

if ($username === '' || $password === '') {
    fwrite(STDERR, "Setup requires a temporary username and password.\n");
    exit(2);
}

$cleanup();
$previousFlags = get_option('am_toolkit_feature_flags', []);
$flags = is_array($previousFlags) ? $previousFlags : [];
$flags['courses'] = true;
update_option('am_toolkit_feature_flags', $flags, false);

$userId = wp_create_user($username, $password, $username . '@example.test');

if (is_wp_error($userId)) {
    throw new RuntimeException($userId->get_error_message());
}

$user = new WP_User((int) $userId);
$user->set_role('customer');
$catalog = new WpdbCourseAdminStore($wpdb);
$mappings = new WpdbProductCourseMappingStore($wpdb);
$service = new CourseAdminService(
    $catalog,
    $mappings,
    new CourseAccessLifecycle($mappings, new AccessCoreCourseEntitlementGateway())
);
$courseIds = [];
$sourceId = 41100;

$value = static function (mixed $result, string $operation): mixed {
    if (is_wp_error($result)) {
        throw new RuntimeException($operation . ': ' . $result->get_error_message());
    }

    return $result;
};

try {
    foreach ([
        ['title' => 'Social media od podstaw', 'description' => 'Praktyczny program prowadzący krok po kroku od strategii do pierwszych regularnych publikacji.', 'state' => 'active'],
        ['title' => 'Marka osobista, która sprzedaje', 'description' => 'Uporządkuj komunikację marki i zamień pomysły w spójną ofertę.', 'state' => 'completed'],
        ['title' => 'Instagram bez chaosu', 'description' => 'Historyczny kurs demonstracyjny.', 'state' => 'expired'],
    ] as $index => $definition) {
        $courseId = (int) $value(
            $service->saveCourse(0, $definition['title'], $definition['description'], 0, PublicationStatus::DRAFT),
            'create course'
        );
        $courseIds[] = $courseId;
        $sectionId = (int) $value(
            $service->saveSection(0, $courseId, 'Moduł 1 — Dobry początek', 'Najważniejsze fundamenty przed przejściem dalej.', 0, PublicationStatus::PUBLISHED),
            'create section'
        );

        foreach (['Ustal swój kierunek', 'Zbuduj prosty plan działania', 'Przygotuj pierwszą publikację'] as $position => $lessonTitle) {
            $value(
                $service->saveLesson(
                    0,
                    $courseId,
                    $sectionId,
                    $lessonTitle,
                    '',
                    null,
                    null,
                    360 + ($position * 180),
                    ['video_percent' => 90],
                    $position,
                    true,
                    PublicationStatus::PUBLISHED
                ),
                'create lesson'
            );
        }

        $value(
            $service->saveCourse($courseId, $definition['title'], $definition['description'], 0, PublicationStatus::PUBLISHED),
            'publish course'
        );
        $sourceId++;
        $context = [
            'source_type' => 'manual',
            'source_id' => $sourceId,
            'grant_key' => 'via41-browser-' . $courseId,
        ];

        if ($definition['state'] === 'expired') {
            $context['expires_at'] = '2020-01-01 00:00:00';
        }

        $value(Access::grant((int) $userId, 'course', $courseId, $context), 'grant access');

        if ($definition['state'] === 'completed') {
            $course = $value($service->course($courseId), 'read course for completion');
            $programId = (int) ($course['current_program_version_id'] ?? 0);
            $requiredLessonIds = array_map('intval', $wpdb->get_col($wpdb->prepare(
                'SELECT lesson_id FROM ' . CoursesSchema::programLessonsTable() . ' WHERE program_version_id = %d AND is_required = 1 ORDER BY position ASC',
                $programId
            )));
            $wpdb->insert(CoursesSchema::completionsTable(), [
                'user_id' => (int) $userId,
                'course_id' => $courseId,
                'program_version_id' => $programId,
                'required_lesson_ids' => wp_json_encode($requiredLessonIds),
                'requirements_hash' => hash('sha256', wp_json_encode($requiredLessonIds)),
                'completion_source' => 'browser_qa',
                'completed_at' => current_time('mysql', true),
                'created_at' => current_time('mysql', true),
            ]);
        }
    }

    update_option($fixtureOption, [
        'course_ids' => $courseIds,
        'user_id' => (int) $userId,
        'previous_flags' => $previousFlags,
    ], false);
    flush_rewrite_rules(false);
    echo "VIA-41 browser fixture setup: OK\n";
} catch (Throwable $error) {
    update_option($fixtureOption, [
        'course_ids' => $courseIds,
        'user_id' => (int) $userId,
        'previous_flags' => $previousFlags,
    ], false);
    $cleanup();
    throw $error;
}
