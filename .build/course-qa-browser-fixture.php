<?php

declare(strict_types=1);

use AMToolkit\Modules\Courses\CoursesSchema;

$mode = $argv[1] ?? '';
$wpLoad = $argv[2] ?? '';
$databaseHost = $argv[3] ?? '';
$coursePublicId = $argv[4] ?? '';

if (!in_array($mode, ['setup', 'cleanup'], true) || $wpLoad === '' || !is_file($wpLoad) || $databaseHost === '' || $coursePublicId === '') {
    fwrite(STDERR, "Usage: php .build/course-qa-browser-fixture.php <setup|cleanup> <wp-load.php> <database-host:port> <course-public-id>\n");
    exit(2);
}

define('DB_HOST', $databaseHost);
set_error_handler(static function (int $severity, string $message): bool {
    return $severity === E_WARNING && str_contains($message, 'Constant DB_HOST already defined');
});
require $wpLoad;
restore_error_handler();

global $wpdb;

$publicIds = [
    '70000000-0000-4000-8000-000000000001',
    '70000000-0000-4000-8000-000000000002',
];
$course = $wpdb->get_row($wpdb->prepare(
    'SELECT id, current_program_version_id FROM ' . CoursesSchema::coursesTable() . ' WHERE public_id = %s LIMIT 1',
    $coursePublicId
), ARRAY_A);

if (!is_array($course)) {
    throw new RuntimeException('Fixture course was not found.');
}

$placeholders = implode(',', array_fill(0, count($publicIds), '%s'));
$deleted = $wpdb->query($wpdb->prepare(
    'DELETE FROM ' . CoursesSchema::qaEntriesTable() . " WHERE course_id = %d AND public_id IN ({$placeholders})",
    (int) $course['id'],
    ...$publicIds
));

if ($deleted === false) {
    throw new RuntimeException('Fixture cleanup failed: ' . $wpdb->last_error);
}

if ($mode === 'cleanup') {
    echo 'VIA-70 browser fixture cleanup: OK (' . $deleted . " entries)\n";
    exit(0);
}

$lesson = $wpdb->get_row($wpdb->prepare(
    'SELECT l.id FROM ' . CoursesSchema::lessonsTable() . ' l'
        . ' INNER JOIN ' . CoursesSchema::programLessonsTable() . ' pl ON pl.lesson_id = l.id'
        . ' WHERE l.course_id = %d AND l.status = %s AND l.archived_at IS NULL AND pl.program_version_id = %d'
        . ' ORDER BY pl.position ASC, pl.id ASC LIMIT 1',
    (int) $course['id'],
    'published',
    (int) $course['current_program_version_id']
), ARRAY_A);
$now = current_time('mysql', true);
$rows = [
    [
        'public_id' => $publicIds[0],
        'course_id' => (int) $course['id'],
        'lesson_id' => is_array($lesson) ? (int) $lesson['id'] : null,
        'question' => 'Czy otrzymam dostęp do nagrania po spotkaniu?',
        'answer' => 'Tak. Gdy nagranie będzie gotowe, właścicielka opublikuje je w kursie.',
        'position' => 10,
        'status' => 'published',
        'created_at' => $now,
        'updated_at' => $now,
        'archived_at' => null,
    ],
    [
        'public_id' => $publicIds[1],
        'course_id' => (int) $course['id'],
        'lesson_id' => null,
        'question' => 'VIA-70 szkic niewidoczny dla uczestniczki',
        'answer' => 'Ten tekst nie może pojawić się w widoku kursu.',
        'position' => 0,
        'status' => 'draft',
        'created_at' => $now,
        'updated_at' => $now,
        'archived_at' => null,
    ],
];

foreach ($rows as $row) {
    if ($wpdb->insert(CoursesSchema::qaEntriesTable(), $row) !== 1) {
        throw new RuntimeException('Fixture setup failed: ' . $wpdb->last_error);
    }
}

echo "VIA-70 browser fixture setup: OK\n";
