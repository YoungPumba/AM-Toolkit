<?php

declare(strict_types=1);

use AMToolkit\Modules\Access\AccessSchema;
use AMToolkit\Modules\Access\WpdbActivityEventStore;
use AMToolkit\Modules\Courses\CoursesSchema;
use AMToolkit\Modules\Courses\Services\AccessCoreCourseAccessPolicy;
use AMToolkit\Modules\Courses\Services\CourseDiagnosticsService;
use AMToolkit\Modules\Courses\Services\CourseProgressService;
use AMToolkit\Modules\Courses\WpdbCompletionRepository;
use AMToolkit\Modules\Courses\WpdbCourseDiagnosticsStore;
use AMToolkit\Modules\Courses\WpdbCourseLessonTaskStore;
use AMToolkit\Modules\Courses\WpdbCourseProgressSourceStore;
use AMToolkit\Modules\Courses\WpdbProgressRepository;

$wpLoad = $argv[1] ?? '';
$databaseHost = $argv[2] ?? '';

if ($wpLoad === '' || !is_file($wpLoad) || $databaseHost === '') {
    fwrite(STDERR, "Usage: php .build/test-course-diagnostics-local.php <wp-load.php> <database-host:port>\n");
    exit(2);
}

define('DB_HOST', $databaseHost);
set_error_handler(static function (int $severity, string $message): bool {
    return $severity === E_WARNING && str_contains($message, 'Constant DB_HOST already defined');
});
require $wpLoad;
restore_error_handler();

global $wpdb;

$events = new WpdbActivityEventStore($wpdb);
$service = new CourseDiagnosticsService(
    new WpdbCourseDiagnosticsStore($wpdb),
    new CourseProgressService(
        new WpdbCourseProgressSourceStore($wpdb),
        new WpdbProgressRepository($wpdb),
        new WpdbCompletionRepository($wpdb),
        new AccessCoreCourseAccessPolicy(),
        $events,
        new WpdbCourseLessonTaskStore($wpdb)
    ),
    $events
);
$value = static function (mixed $result, string $operation): mixed {
    if (is_wp_error($result)) {
        throw new RuntimeException($operation . ': ' . $result->get_error_code() . ' — ' . $result->get_error_message());
    }

    return $result;
};

$health = $value($service->health(), 'schema health');

if (empty($health['valid'])) {
    throw new RuntimeException('Course schema health check reported an inconsistency: ' . wp_json_encode($health));
}

$target = $wpdb->get_row(
    "SELECT g.user_id, g.resource_id AS course_id
    FROM " . AccessSchema::grantsTable() . " g
    INNER JOIN " . CoursesSchema::coursesTable() . " c ON c.id = g.resource_id
    WHERE g.resource_type = 'course' AND g.status = 'active'
      AND (g.starts_at IS NULL OR g.starts_at <= UTC_TIMESTAMP())
      AND (g.expires_at IS NULL OR g.expires_at > UTC_TIMESTAMP())
      AND c.current_program_version_id IS NOT NULL
    ORDER BY g.id DESC LIMIT 1",
    ARRAY_A
);

if (is_array($target)) {
    $userId = (int) $target['user_id'];
    $courseId = (int) $target['course_id'];
    $diagnostics = $value($service->inspect($userId, $courseId), 'participant diagnostics');
    $json = $value($service->export($userId, $courseId), 'safe export');

    if (str_contains($json, '"user_id"') || !str_contains($json, '"user_ref"')) {
        throw new RuntimeException('Diagnostic export did not pseudonymize the participant.');
    }

    echo sprintf(
        "VIA-46 local read-only diagnostics: OK (user_ref only, course %d, issues %d: %s)\n",
        $courseId,
        count((array) ($diagnostics['issues'] ?? [])),
        implode(', ', array_map('strval', array_column((array) ($diagnostics['issues'] ?? []), 'code'))) ?: 'none'
    );
    exit(0);
}

echo "VIA-46 local schema diagnostics: OK (no active participant/course pair found)\n";
