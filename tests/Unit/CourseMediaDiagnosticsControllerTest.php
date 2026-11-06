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
    public function testMediaElementRequiresExplicitDiagnosticOptIn(array $query, string $expected): void
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
            'ordinary page' => [[], 'native'],
            'native alone' => [['am_course_player' => 'native'], 'native'],
            'mediaelement alone' => [['am_course_player' => 'mediaelement'], 'native'],
            'diagnostics alone' => [['am_course_diagnostics' => '1'], 'native'],
            'diagnostics off' => [['am_course_diagnostics' => '0', 'am_course_player' => 'mediaelement'], 'native'],
            'native enabled' => [['am_course_diagnostics' => '1', 'am_course_player' => 'native'], 'native'],
            'explicit comparison' => [['am_course_diagnostics' => '1', 'am_course_player' => 'mediaelement'], 'mediaelement'],
            'unknown mode' => [['am_course_diagnostics' => '1', 'am_course_player' => 'unknown'], 'native'],
            'array mode' => [['am_course_diagnostics' => '1', 'am_course_player' => ['mediaelement']], 'native'],
            'array diagnostics' => [['am_course_diagnostics' => ['1'], 'am_course_player' => 'mediaelement'], 'native'],
        ];
    }
}
