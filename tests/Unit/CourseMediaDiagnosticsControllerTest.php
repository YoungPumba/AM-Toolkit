<?php

declare(strict_types=1);

use AMToolkit\Modules\Courses\Contracts\CourseAccessPolicy;
use AMToolkit\Modules\Courses\Contracts\CourseViewStore;
use AMToolkit\Modules\Courses\Frontend\CourseMediaDiagnosticsController;
use AMToolkit\Modules\Courses\Services\CourseCatalogService;
use AMToolkit\Modules\Courses\Services\CourseMediaDiagnosticsService;
use PHPUnit\Framework\TestCase;

final class CourseMediaDiagnosticsControllerTest extends TestCase
{
    /** @dataProvider playerRequests */
    public function testNativePlayerRequiresExplicitDiagnosticOptIn(array $query, string $expected): void
    {
        $previous = $_GET;
        $_GET = $query;
        try {
            $controller = new CourseMediaDiagnosticsController(
                new CourseCatalogService(
                    $this->createMock(CourseViewStore::class),
                    $this->createMock(CourseAccessPolicy::class)
                ),
                new CourseMediaDiagnosticsService()
            );
            self::assertSame($expected, $controller->playerMode());
        } finally {
            $_GET = $previous;
        }
    }

    public function playerRequests(): array
    {
        return [
            'ordinary page' => [[], 'mediaelement'],
            'native alone' => [['am_course_player' => 'native'], 'mediaelement'],
            'diagnostics alone' => [['am_course_diagnostics' => '1'], 'mediaelement'],
            'diagnostics off' => [['am_course_diagnostics' => '0', 'am_course_player' => 'native'], 'mediaelement'],
            'native enabled' => [['am_course_diagnostics' => '1', 'am_course_player' => 'native'], 'native'],
            'explicit standard' => [['am_course_diagnostics' => '1', 'am_course_player' => 'mediaelement'], 'mediaelement'],
            'unknown mode' => [['am_course_diagnostics' => '1', 'am_course_player' => 'unknown'], 'mediaelement'],
            'array mode' => [['am_course_diagnostics' => '1', 'am_course_player' => ['native']], 'mediaelement'],
            'array diagnostics' => [['am_course_diagnostics' => ['1'], 'am_course_player' => 'native'], 'mediaelement'],
        ];
    }
}
