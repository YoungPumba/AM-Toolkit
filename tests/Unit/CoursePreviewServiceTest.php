<?php

declare(strict_types=1);

namespace AMToolkit\Tests\Unit;

use AMToolkit\Modules\Courses\Contracts\CourseAdminStore;
use AMToolkit\Modules\Courses\Contracts\CourseEntitlementGateway;
use AMToolkit\Modules\Courses\Contracts\ProductCourseMappingStore;
use AMToolkit\Modules\Courses\Services\CourseAccessLifecycle;
use AMToolkit\Modules\Courses\Services\CourseAdminService;
use AMToolkit\Modules\Courses\Services\CoursePreviewService;
use PHPUnit\Framework\TestCase;

final class CoursePreviewServiceTest extends TestCase
{
    private PreviewAdminStore $store;
    private CoursePreviewService $preview;

    protected function setUp(): void
    {
        $this->store = new PreviewAdminStore();
        $mapping = new PreviewMappingStore();
        $admin = new CourseAdminService(
            $this->store,
            $mapping,
            new CourseAccessLifecycle($mapping, new PreviewEntitlementGateway())
        );
        $this->preview = new CoursePreviewService($admin);
    }

    public function testBuildsCurrentWorkspaceInsteadOfPublishedAccessView(): void
    {
        $this->store->sections = [
            ['id' => 7, 'public_id' => $this->uuid(7), 'title' => 'Start', 'description' => '', 'position' => 0, 'status' => 'draft'],
            ['id' => 8, 'public_id' => $this->uuid(8), 'title' => 'Stara', 'description' => '', 'position' => 1, 'status' => 'archived'],
        ];
        $this->store->lessons = [
            ['id' => 11, 'public_id' => $this->uuid(11), 'course_id' => 4, 'section_id' => 7, 'section_title' => 'Start', 'title' => 'Kierunek', 'duration_seconds' => 120, 'position' => 0, 'is_required' => 1, 'status' => 'draft'],
            ['id' => 12, 'public_id' => $this->uuid(12), 'course_id' => 4, 'section_id' => null, 'title' => 'Ukryta', 'position' => 1, 'is_required' => 1, 'status' => 'archived'],
        ];

        $result = $this->preview->course(4);

        self::assertIsArray($result);
        self::assertSame('Kurs roboczy', $result['title']);
        self::assertCount(1, $result['program']['sections']);
        self::assertSame('Kierunek', $result['program']['sections'][0]['lessons'][0]['title']);
        self::assertSame([], $result['program']['lessons']);
        self::assertArrayNotHasKey('id', $result);
    }

    public function testLessonPreviewIncludesDraftMaterialsAndNavigationWithoutProgress(): void
    {
        $this->store->lessons = [
            ['id' => 11, 'public_id' => $this->uuid(11), 'course_id' => 4, 'section_id' => null, 'section_title' => 'Start', 'title' => 'Pierwsza', 'description' => 'Opis', 'duration_seconds' => 120, 'position' => 0, 'is_required' => 1, 'status' => 'draft', 'video_provider' => 'am-private', 'video_reference' => 'video.mp4', 'completion_requirements' => ['video_percent' => 90]],
            ['id' => 12, 'public_id' => $this->uuid(12), 'course_id' => 4, 'section_id' => null, 'section_title' => 'Start', 'title' => 'Druga', 'duration_seconds' => 60, 'position' => 1, 'is_required' => 1, 'status' => 'draft'],
        ];
        $this->store->materials = [[
            'id' => 31,
            'public_id' => $this->uuid(31),
            'lesson_id' => 11,
            'name' => 'Ćwiczenia',
            'storage_provider' => 'am-private',
            'storage_reference' => 'material.pdf',
            'status' => 'draft',
        ]];

        $lesson = $this->preview->lesson(4, $this->uuid(11));
        $asset = $this->preview->asset(4, $this->uuid(11), 'material', $this->uuid(31));

        self::assertIsArray($lesson);
        self::assertSame('Kurs roboczy', $lesson['course']['title']);
        self::assertSame('Druga', $lesson['next']['title']);
        self::assertCount(2, $lesson['program_lessons']);
        self::assertSame('Ćwiczenia', $lesson['materials'][0]['name']);
        self::assertArrayNotHasKey('id', $lesson);
        self::assertIsArray($asset);
        self::assertSame('material.pdf', $asset['reference']);
    }

    public function testUnknownLessonReturnsSafePreviewError(): void
    {
        $result = $this->preview->lesson(4, $this->uuid(99));

        self::assertInstanceOf(\WP_Error::class, $result);
        self::assertSame('am_toolkit_course_preview_lesson_not_found', $result->get_error_code());
    }

    private function uuid(int $suffix): string
    {
        return sprintf('12345678-1234-4123-8123-%012d', $suffix);
    }
}

final class PreviewAdminStore implements CourseAdminStore
{
    /** @var list<array<string, mixed>> */
    public array $sections = [];

    /** @var list<array<string, mixed>> */
    public array $lessons = [];

    /** @var list<array<string, mixed>> */
    public array $materials = [];

    public function listCourses(): array|\WP_Error { return []; }
    public function findCourse(int $courseId): array|null|\WP_Error
    {
        return $courseId === 4 ? [
            'id' => 4,
            'public_id' => '12345678-1234-4123-8123-000000000004',
            'title' => 'Kurs roboczy',
            'description' => 'Opis kursu',
            'image_attachment_id' => 0,
            'status' => 'draft',
        ] : null;
    }
    public function saveCourse(int $courseId, string $title, string $description, int $imageAttachmentId, string $status): int|\WP_Error { return 4; }
    public function archiveCourse(int $courseId): bool|\WP_Error { return true; }
    public function sectionsForCourse(int $courseId): array|\WP_Error { return $this->sections; }
    public function saveSection(int $sectionId, int $courseId, string $title, string $description, int $position, string $status): int|\WP_Error { return 7; }
    public function archiveSection(int $sectionId, int $courseId): bool|\WP_Error { return true; }
    public function lessonsForCourse(int $courseId): array|\WP_Error { return $this->lessons; }
    public function saveLesson(int $lessonId, int $courseId, ?int $sectionId, string $title, string $description, ?string $videoProvider, ?string $videoReference, ?int $durationSeconds, array $completionRequirements, int $position, bool $required, string $status): int|\WP_Error { return 11; }
    public function archiveLesson(int $lessonId, int $courseId): bool|\WP_Error { return true; }
    public function materialsForCourse(int $courseId): array|\WP_Error { return $this->materials; }
    public function saveMaterial(int $materialId, int $lessonId, string $name, string $description, string $storageProvider, string $storageReference, int $position, string $status): int|\WP_Error { return 31; }
    public function archiveMaterial(int $materialId, int $lessonId): bool|\WP_Error { return true; }
    public function participantsForCourse(int $courseId): array|\WP_Error { return []; }
    public function activityForCourse(int $courseId, int $limit = 50): array|\WP_Error { return []; }
}

final class PreviewMappingStore implements ProductCourseMappingStore
{
    public function map(int $productId, int $courseId): bool|\WP_Error { return true; }
    public function unmap(int $productId, int $courseId): bool|\WP_Error { return true; }
    public function courseIdsForProducts(array $productIds): array|\WP_Error { return []; }
    public function productIdsForCourse(int $courseId): array|\WP_Error { return []; }
}

final class PreviewEntitlementGateway implements CourseEntitlementGateway
{
    public function grant(int $userId, int $courseId, array $context): int|\WP_Error { return 1; }
    public function revokeAllSource(string $sourceType, int $sourceId, array $context): int|\WP_Error { return 1; }
}
