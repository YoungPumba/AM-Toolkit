<?php

declare(strict_types=1);

use AMToolkit\Modules\Access\WpdbActivityEventStore;
use AMToolkit\Modules\Courses\CoursesSchema;
use AMToolkit\Modules\Courses\Services\CourseQaService;
use AMToolkit\Modules\Courses\WpdbCourseQaStore;

$wpLoad = $argv[1] ?? '';
$databaseHost = $argv[2] ?? '';

if ($wpLoad === '' || !is_file($wpLoad) || $databaseHost === '') {
    fwrite(STDERR, "Usage: php .build/test-course-qa-local.php <wp-load.php> <database-host:port>\n");
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
        throw new RuntimeException('Insert failed for synthetic VIA-70 data: ' . $wpdb->last_error);
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
$wpdb->query('START TRANSACTION');

try {
    $courseId = $insert(CoursesSchema::coursesTable(), [
        'public_id' => wp_generate_uuid4(),
        'title' => 'VIA-70 kurs QA',
        'description' => 'Dane syntetyczne',
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
        'content_hash' => hash('sha256', 'via-70-program'),
        'published_at' => $now,
        'created_at' => $now,
    ]);
    $lessonId = $insert(CoursesSchema::lessonsTable(), [
        'public_id' => wp_generate_uuid4(),
        'course_id' => $courseId,
        'title' => 'Lekcja kontekstowa VIA-70',
        'description' => '',
        'status' => 'published',
        'video_provider' => null,
        'video_reference' => null,
        'duration_seconds' => null,
        'completion_requirements' => '{}',
        'content_version' => 1,
        'created_at' => $now,
        'updated_at' => $now,
        'archived_at' => null,
    ]);
    $insert(CoursesSchema::programLessonsTable(), [
        'program_version_id' => $programId,
        'lesson_id' => $lessonId,
        'section_id' => null,
        'position' => 0,
        'is_required' => 1,
    ]);
    $wpdb->update(CoursesSchema::coursesTable(), ['current_program_version_id' => $programId], ['id' => $courseId]);

    $store = new WpdbCourseQaStore($wpdb);
    $service = new CourseQaService($store, new WpdbActivityEventStore($wpdb));
    $publishedId = $value($service->save([
        'course_id' => $courseId,
        'lesson_id' => $lessonId,
        'question' => 'Czy ten wpis jest widoczny?',
        'answer' => 'Tak, ponieważ jest opublikowany.',
        'position' => 20,
        'status' => 'published',
    ], 1, 'via70published000000000000000'), 'save published Q&A');
    $value($service->save([
        'course_id' => $courseId,
        'question' => 'Czy szkic wycieknie?',
        'answer' => 'Nie.',
        'position' => 0,
        'status' => 'draft',
    ], 1, 'via70draft000000000000000000'), 'save draft Q&A');
    $archivedId = $value($service->save([
        'course_id' => $courseId,
        'question' => 'Wpis do archiwum',
        'answer' => 'Zostanie zachowany.',
        'position' => 10,
        'status' => 'published',
    ], 1, 'via70archive000000000000000'), 'save Q&A for archive');
    $value($service->archive($archivedId, $courseId, 1, 'via70archived00000000000000'), 'archive Q&A');

    $adminEntries = $value($service->entries($courseId), 'read editorial Q&A');
    $expect(count($adminEntries) === 3, 'Editorial view did not preserve all Q&A states.');
    $participantEntries = $value($store->publishedEntriesForCourse($courseId, $programId), 'read participant Q&A');
    $expect(count($participantEntries) === 1, 'Draft or archived Q&A leaked into participant view.');
    $expect((int) $publishedId > 0, 'Published Q&A did not receive an identifier.');
    $expect(($participantEntries[0]['question'] ?? '') === 'Czy ten wpis jest widoczny?', 'Unexpected participant Q&A order or content.');
    $expect(($participantEntries[0]['lesson_title'] ?? '') === 'Lekcja kontekstowa VIA-70', 'Published lesson context is missing.');
    $expect(!array_key_exists('status', $participantEntries[0]), 'Participant view exposed editorial status.');
    $expect(!array_key_exists('course_id', $participantEntries[0]), 'Participant view exposed internal course ID.');

    echo "VIA-70 local integration: OK\n";
} finally {
    $wpdb->query('ROLLBACK');
}
