<?php

declare(strict_types=1);

use AMToolkit\Modules\Access\AccessSchema;
use AMToolkit\Modules\Courses\CoursesSchema;
use AMToolkit\Modules\Courses\Services\AccessCoreCourseAccessPolicy;
use AMToolkit\Modules\Courses\Services\CourseCatalogService;
use AMToolkit\Modules\Courses\WpdbCourseViewStore;

$wpLoad = $argv[1] ?? '';
$databaseHost = $argv[2] ?? '';

if ($wpLoad === '' || !is_file($wpLoad) || $databaseHost === '') {
    fwrite(STDERR, "Usage: php .build/test-course-hub-local.php <wp-load.php> <database-host:port>\n");
    exit(2);
}

define('DB_HOST', $databaseHost);
set_error_handler(static function (int $severity, string $message): bool {
    return $severity === E_WARNING && str_contains($message, 'Constant DB_HOST already defined');
});
require $wpLoad;
restore_error_handler();

global $wpdb;

$expect = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};
$insert = static function (string $table, array $data) use ($wpdb): int {
    if ($wpdb->insert($table, $data) !== 1) {
        throw new RuntimeException('Insert failed for synthetic VIA-41 data.');
    }

    return (int) $wpdb->insert_id;
};
$value = static function (mixed $result, string $operation): mixed {
    if (is_wp_error($result)) {
        throw new RuntimeException($operation . ': ' . $result->get_error_code() . ' — ' . $result->get_error_message());
    }

    return $result;
};

$now = current_time('mysql', true);
$userId = 2000000041;
$otherUserId = 2000000042;
$courseUuid = wp_generate_uuid4();
$expiredCourseUuid = wp_generate_uuid4();
$wpdb->query('START TRANSACTION');

try {
    $courseId = $insert(CoursesSchema::coursesTable(), [
        'public_id' => $courseUuid,
        'title' => 'VIA-41 aktywny kurs QA',
        'description' => 'Opis dostępny wyłącznie po autoryzacji.',
        'image_attachment_id' => 0,
        'status' => 'published',
        'current_program_version_id' => null,
        'created_at' => $now,
        'updated_at' => $now,
        'archived_at' => null,
    ]);
    $programId = $insert(CoursesSchema::programVersionsTable(), [
        'public_id' => wp_generate_uuid4(),
        'course_id' => $courseId,
        'version_number' => 1,
        'status' => 'published',
        'content_hash' => hash('sha256', 'via-41-program'),
        'published_at' => $now,
        'created_at' => $now,
    ]);
    $wpdb->update(
        CoursesSchema::coursesTable(),
        ['current_program_version_id' => $programId],
        ['id' => $courseId],
        ['%d'],
        ['%d']
    );
    $sectionId = $insert(CoursesSchema::sectionsTable(), [
        'public_id' => wp_generate_uuid4(),
        'program_version_id' => $programId,
        'title' => 'Sekcja widoczna',
        'description' => 'Bezpieczny opis sekcji.',
        'position' => 0,
        'status' => 'published',
        'created_at' => $now,
        'updated_at' => $now,
        'archived_at' => null,
    ]);
    $lessonId = $insert(CoursesSchema::lessonsTable(), [
        'public_id' => wp_generate_uuid4(),
        'course_id' => $courseId,
        'title' => 'Lekcja opublikowana',
        'description' => '',
        'status' => 'published',
        'video_provider' => null,
        'video_reference' => null,
        'duration_seconds' => 420,
        'completion_requirements' => null,
        'content_version' => 1,
        'created_at' => $now,
        'updated_at' => $now,
        'archived_at' => null,
    ]);
    $draftLessonId = $insert(CoursesSchema::lessonsTable(), [
        'public_id' => wp_generate_uuid4(),
        'course_id' => $courseId,
        'title' => 'Lekcja robocza nie może wyciec',
        'description' => '',
        'status' => 'draft',
        'video_provider' => null,
        'video_reference' => null,
        'duration_seconds' => null,
        'completion_requirements' => null,
        'content_version' => 1,
        'created_at' => $now,
        'updated_at' => $now,
        'archived_at' => null,
    ]);
    $insert(CoursesSchema::programLessonsTable(), [
        'program_version_id' => $programId,
        'lesson_id' => $lessonId,
        'section_id' => $sectionId,
        'position' => 0,
        'is_required' => 1,
    ]);
    $insert(CoursesSchema::programLessonsTable(), [
        'program_version_id' => $programId,
        'lesson_id' => $draftLessonId,
        'section_id' => $sectionId,
        'position' => 1,
        'is_required' => 1,
    ]);

    $expiredCourseId = $insert(CoursesSchema::coursesTable(), [
        'public_id' => $expiredCourseUuid,
        'title' => 'VIA-41 wygasły kurs QA',
        'description' => 'Ten opis nie może zostać odczytany po wygaśnięciu.',
        'image_attachment_id' => 0,
        'status' => 'published',
        'current_program_version_id' => null,
        'created_at' => $now,
        'updated_at' => $now,
        'archived_at' => null,
    ]);
    $expiredProgramId = $insert(CoursesSchema::programVersionsTable(), [
        'public_id' => wp_generate_uuid4(),
        'course_id' => $expiredCourseId,
        'version_number' => 1,
        'status' => 'published',
        'content_hash' => hash('sha256', 'via-41-expired-program'),
        'published_at' => $now,
        'created_at' => $now,
    ]);
    $wpdb->update(
        CoursesSchema::coursesTable(),
        ['current_program_version_id' => $expiredProgramId],
        ['id' => $expiredCourseId],
        ['%d'],
        ['%d']
    );

    $grantBase = [
        'user_id' => $userId,
        'resource_type' => 'course',
        'source_type' => 'manual',
        'source_id' => 41001,
        'status' => 'active',
        'starts_at' => null,
        'granted_at' => $now,
        'revoked_at' => null,
        'metadata' => null,
        'created_at' => $now,
        'updated_at' => $now,
    ];
    $insert(AccessSchema::grantsTable(), $grantBase + [
        'resource_id' => $courseId,
        'grant_key' => 'via41-active-' . wp_generate_uuid4(),
        'expires_at' => null,
    ]);
    $insert(AccessSchema::grantsTable(), array_merge($grantBase, [
        'resource_id' => $expiredCourseId,
        'grant_key' => 'via41-expired-' . wp_generate_uuid4(),
        'source_id' => 41002,
        'expires_at' => '2020-01-01 00:00:00',
    ]));

    $service = new CourseCatalogService(
        new WpdbCourseViewStore($wpdb),
        new AccessCoreCourseAccessPolicy()
    );
    $courses = $value($service->coursesForUser($userId), 'list own courses');
    $expect(count($courses) === 2, 'The hub did not return exactly the two synthetic own courses.');
    $states = array_column($courses, 'access_state', 'public_id');
    $expect(($states[$courseUuid] ?? '') === 'active', 'Active course state is incorrect.');
    $expect(($states[$expiredCourseUuid] ?? '') === 'expired', 'Expired course state is incorrect.');

    $course = $value($service->courseForUser($userId, $courseUuid), 'read active course');
    $expect(($course['description'] ?? '') === 'Opis dostępny wyłącznie po autoryzacji.', 'Authorized course details are incomplete.');
    $expect(count($course['program']['sections'] ?? []) === 1, 'Published section is missing.');
    $expect(count($course['program']['sections'][0]['lessons'] ?? []) === 1, 'Draft lesson leaked or published lesson is missing.');
    $expect(($course['program']['sections'][0]['lessons'][0]['title'] ?? '') === 'Lekcja opublikowana', 'Unexpected lesson in participant program.');

    $expired = $service->courseForUser($userId, $expiredCourseUuid);
    $expect(is_wp_error($expired) && $expired->get_error_code() === 'am_toolkit_course_not_available', 'Expired access exposed the private program.');
    $foreign = $service->courseForUser($otherUserId, $courseUuid);
    $expect(is_wp_error($foreign) && $foreign->get_error_code() === 'am_toolkit_course_not_available', 'Another user could read the private program.');
    $invalid = $service->courseForUser($userId, '../../invalid');
    $expect(is_wp_error($invalid) && $invalid->get_error_code() === 'am_toolkit_course_not_available', 'Invalid public ID did not return the safe state.');

    echo "VIA-41 local integration: OK\n";
} finally {
    $wpdb->query('ROLLBACK');
}
