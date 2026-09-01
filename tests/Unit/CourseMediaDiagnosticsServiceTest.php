<?php

declare(strict_types=1);

namespace AMToolkit\Tests\Unit;

use AMToolkit\Modules\Courses\Services\CourseMediaDiagnosticsService;
use PHPUnit\Framework\TestCase;

final class CourseMediaDiagnosticsServiceTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['amt_test_transients'] = [];
        $GLOBALS['amt_test_transient_expirations'] = [];
    }

    public function testSessionIdentifiersAreRandomAndStrictlyValidated(): void
    {
        $service = new CourseMediaDiagnosticsService();
        $first = $service->createSessionId();
        $second = $service->createSessionId();

        self::assertNotSame($first, $second);
        self::assertTrue($service->isValidSessionId($first));
        self::assertFalse($service->isValidSessionId(strtolower($first)));
        self::assertFalse($service->isValidSessionId('AMD-not-a-session'));
    }

    public function testRangeLogIsBoundedAndDropsUnapprovedFields(): void
    {
        $service = new CourseMediaDiagnosticsService();
        $session = $service->createSessionId();

        for ($index = 0; $index < 125; $index++) {
            $service->recordRange(7, $session, [
                'request_id' => 'AMR-' . $index,
                'phase' => 'end',
                'method' => 'GET',
                'status' => 206,
                'partial' => true,
                'range_header_present' => true,
                'range_header_source' => 'redirect_http_range',
                'range_start' => $index,
                'range_end' => $index + 99,
                'range_length' => 100,
                'resource_size' => 1000,
                'bytes_sent' => 100,
                'duration_ms' => 20,
                'connection_status' => 0,
                'completed' => true,
                'source_url' => 'https://example.test/video.mp4?nonce=secret',
                'cookie' => 'wordpress_logged_in=secret',
                'range_header_value' => 'bytes=secret-',
            ]);
        }

        $report = $service->report(7, $session, 'course-id', 'lesson-id', [], [], '0.12.3');
        $encoded = json_encode($report, JSON_THROW_ON_ERROR);

        self::assertCount(120, $report['server_range_requests']);
        self::assertSame('AMR-5', $report['server_range_requests'][0]['request_id']);
        self::assertTrue($report['server_range_requests'][0]['range_header_present']);
        self::assertSame('redirect_http_range', $report['server_range_requests'][0]['range_header_source']);
        self::assertStringNotContainsString('example.test', $encoded);
        self::assertStringNotContainsString('wordpress_logged_in', $encoded);
        self::assertStringNotContainsString('range_header_value', $encoded);
        self::assertStringNotContainsString('secret', $encoded);
    }

    public function testReportSanitizesClientPayloadAndPseudonymizesObjects(): void
    {
        $service = new CourseMediaDiagnosticsService();
        $session = $service->createSessionId();
        $events = [[
            'event' => '<b>waiting</b>',
            'recorded_at_utc' => '2026-09-01T21:30:00.000Z',
            'at_ms' => 1500,
            'current_time' => 610.1254,
            'duration' => 925.5,
            'paused' => false,
            'ready_state' => 2,
            'network_state' => 2,
            'buffered' => [[0, 611.5]],
            'seekable' => [[0, 925.5]],
            'source_url' => 'https://example.test/private-video',
        ]];

        $report = $service->report(9, $session, 'course-secret', 'lesson-secret', $events, [
            'user_agent' => 'Mobile Safari',
            'platform' => 'iPhone',
            'viewport_width' => 390,
            'viewport_height' => 844,
            'connection_effective_type' => '4g',
            'cookie' => 'secret',
        ], '0.12.3');
        $encoded = json_encode($report, JSON_THROW_ON_ERROR);

        self::assertSame('waiting', $report['client_events'][0]['event']);
        self::assertSame('2026-09-01T21:30:00.000Z', $report['client_events'][0]['recorded_at_utc']);
        self::assertSame(610.125, $report['client_events'][0]['current_time']);
        self::assertSame([[0.0, 611.5]], $report['client_events'][0]['buffered']);
        self::assertSame('Mobile Safari', $report['environment']['user_agent']);
        self::assertSame('4g', $report['environment']['connection_effective_type']);
        self::assertStringNotContainsString('course-secret', $encoded);
        self::assertStringNotContainsString('lesson-secret', $encoded);
        self::assertStringNotContainsString('example.test', $encoded);
        self::assertStringNotContainsString('cookie', $encoded);
    }
}
