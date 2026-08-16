<?php

declare(strict_types=1);

use AMToolkit\Modules\Access\AccessSchema;
use AMToolkit\Modules\Courses\CoursesSchema;
use AMToolkit\Modules\Courses\Domain\PublicationStatus;
use AMToolkit\Modules\Courses\Services\AccessCoreCourseEntitlementGateway;
use AMToolkit\Modules\Courses\Services\CourseAccessLifecycle;
use AMToolkit\Modules\Courses\Services\CourseAdminService;
use AMToolkit\Modules\Courses\WpdbCourseAdminStore;
use AMToolkit\Modules\Courses\WpdbProductCourseMappingStore;

$wpLoad = $argv[1] ?? '';
$databaseHost = $argv[2] ?? '';

if ($wpLoad === '' || !is_file($wpLoad) || $databaseHost === '') {
    fwrite(STDERR, "Usage: php .build/test-course-admin-local.php <wp-load.php> <database-host:port>\n");
    exit(2);
}

define('DB_HOST', $databaseHost);
set_error_handler(static function (int $severity, string $message): bool {
    return $severity === E_WARNING && str_contains($message, 'Constant DB_HOST already defined');
});
require $wpLoad;
restore_error_handler();

global $wpdb;

$catalog = new WpdbCourseAdminStore($wpdb);
$mappings = new WpdbProductCourseMappingStore($wpdb);
$lifecycle = new CourseAccessLifecycle($mappings, new AccessCoreCourseEntitlementGateway());
$service = new CourseAdminService(
    $catalog,
    $mappings,
    $lifecycle
);
$courseId = 0;
$lessonIds = [];
$programIds = [];

$expect = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};
$value = static function (mixed $result, string $operation): mixed {
    if (is_wp_error($result)) {
        throw new RuntimeException($operation . ': ' . $result->get_error_code() . ' — ' . $result->get_error_message());
    }

    return $result;
};

try {
    $courseId = (int) $value(
        $service->saveCourse(0, 'VIA-30 QA ' . gmdate('His'), 'Kurs tworzony przez test integracyjny.', 0, PublicationStatus::DRAFT),
        'create course'
    );
    $firstSection = (int) $value($service->saveSection(0, $courseId, 'Pierwsza', '', 0, PublicationStatus::DRAFT), 'create first section');
    $secondSection = (int) $value($service->saveSection(0, $courseId, 'Druga', '', 0, PublicationStatus::DRAFT), 'create and reorder second section');
    $sections = $value($service->sections($courseId), 'read sections');
    $expect(array_map('intval', array_column($sections, 'id')) === [$secondSection, $firstSection], 'Section reorder did not move the new section to position 0.');
    $expect(array_map('intval', array_column($sections, 'position')) === [0, 1], 'Section positions are not contiguous.');

    $lessonId = (int) $value(
        $service->saveLesson(
            0,
            $courseId,
            $secondSection,
            'Lekcja testowa',
            'Opis etapu',
            'vimeo',
            'qa-video-reference',
            600,
            ['video_percent' => 90, 'task_required' => true],
            0,
            true,
            PublicationStatus::DRAFT
        ),
        'create lesson'
    );
    $lessonIds[] = $lessonId;
    $materialId = (int) $value(
        $service->saveMaterial(0, $lessonId, 'Ćwiczenia PDF', 'Materiał QA', 'wordpress', 'qa-material-reference', 0, PublicationStatus::DRAFT),
        'create material'
    );
    $expect($materialId > 0, 'Material was not created.');

    $value($service->replaceProductMappings($courseId, [700000001, 700000002]), 'map products');
    $expect($service->productIds($courseId) === [700000001, 700000002], 'Product mapping replacement is inconsistent.');

    $orderId = random_int(100000000, 999999999);
    $purchaseGrants = $value(
        $lifecycle->grantPurchase(1, $orderId, [700000001], [], 'AM-20260816-PURCHASE0001'),
        'grant purchase access'
    );
    $expect(count($purchaseGrants) === 1, 'Purchase did not create exactly one mapped course grant.');
    $expect(
        $value($lifecycle->revokePurchase($orderId, 'AM-20260816-REFUND000001'), 'revoke refunded purchase') === 1,
        'Refund did not revoke the purchase grant.'
    );
    $restoredPurchaseGrants = $value(
        $lifecycle->grantPurchase(1, $orderId, [700000001], [], 'AM-20260816-REPAID0000001'),
        'restore repaid purchase access'
    );
    $expect($restoredPurchaseGrants === $purchaseGrants, 'Repaid order did not restore the same idempotent grant.');
    $expect(
        $value($lifecycle->revokePurchase($orderId, 'AM-20260816-CLEANUP00001'), 'clean purchase access') === 1,
        'Purchase cleanup did not revoke the restored grant.'
    );

    $assignmentId = random_int(100000000, 999999999);
    $value($service->grantManual(1, $courseId, $assignmentId, 'AM-20260815-00000000A030'), 'grant manual access');
    $participants = $value($service->participants($courseId), 'read participants');
    $activeParticipants = array_values(array_filter(
        $participants,
        static fn (array $participant): bool => $participant['status'] === 'active'
    ));
    $expect(
        count($activeParticipants) === 1
            && $activeParticipants[0]['source_type'] === 'manual'
            && (int) $activeParticipants[0]['source_id'] === $assignmentId,
        'The active manual participant grant is missing.'
    );
    $value($service->revokeManual($assignmentId, 'AM-20260815-00000000A031'), 'revoke manual access');
    $activity = $value($service->activity($courseId), 'read access history');
    $expect(
        array_column(array_slice($activity, 0, 2), 'event_type') === ['access.revoked', 'access.granted'],
        'The latest manual access history is incomplete or unordered.'
    );

    $value($service->saveCourse($courseId, 'VIA-30 QA published', 'Po publikacji.', 0, PublicationStatus::PUBLISHED), 'publish course');
    $course = $value($service->course($courseId), 'read published course');
    $expect(is_array($course) && $course['status'] === PublicationStatus::PUBLISHED, 'Course was not published.');
    $expect((int) ($course['draft_program_version_id'] ?? 0) > 0, 'Publishing did not create the next draft program.');

    $draftSections = $value($service->sections($courseId), 'read cloned draft sections');
    $draftLessons = $value($service->lessons($courseId), 'read cloned draft lessons');
    $expect(count($draftSections) === 2 && count($draftLessons) === 1, 'Published program was not cloned into the next draft.');

    $value($service->archiveMaterial($materialId, $lessonId), 'archive material');
    $value($service->archiveLesson($lessonId, $courseId), 'archive lesson');
    $value($service->archiveSection((int) $draftSections[0]['id'], $courseId), 'archive section');
    $value($service->archiveCourse($courseId), 'archive course');
    $course = $value($service->course($courseId), 'read archived course');
    $expect(is_array($course) && $course['status'] === PublicationStatus::ARCHIVED, 'Course archive did not preserve an archived record.');

    echo "VIA-30/VIA-45 local integration: OK\n";
} finally {
    if ($courseId > 0) {
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
}
