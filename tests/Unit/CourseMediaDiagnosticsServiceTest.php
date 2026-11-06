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

    public function testReportIdentifiesComparisonModeWithoutAcceptingArbitraryLabels(): void
    {
        $service = new CourseMediaDiagnosticsService();
        $session = $service->createSessionId();
        $report = $service->report(9, $session, 'course', 'lesson', [
            ['event' => 'seeking', 'is_trusted' => true, 'seeking' => true],
            ['event' => 'playing', 'is_trusted' => false],
            ['event' => 'diagnostics-export'],
            ['event' => 'playing', 'is_trusted' => 'private-url'],
        ], [
            'player_mode' => 'native',
            'mediaelement_present' => false,
            'client_events_dropped' => 54,
        ], 'test');
        self::assertSame('native', $report['environment']['player_mode']);
        self::assertFalse($report['environment']['mediaelement_present']);
        self::assertSame(54, $report['environment']['client_events_dropped']);
        self::assertSame([true, false, null, null], array_column($report['client_events'], 'is_trusted'));
        self::assertTrue($report['client_events'][0]['seeking']);

        $invalid = $service->report(9, $session, 'course', 'lesson', [], [
            'player_mode' => 'https://example.test/private?nonce=secret',
            'mediaelement_present' => 'secret',
            'client_events_dropped' => -12,
            'diagnostics_performance_origin_ms' => 'secret',
        ], 'test');
        self::assertSame('', $invalid['environment']['player_mode']);
        self::assertNull($invalid['environment']['mediaelement_present']);
        self::assertSame(0, $invalid['environment']['client_events_dropped']);
        self::assertSame(0.0, $invalid['environment']['diagnostics_performance_origin_ms']);
        self::assertStringNotContainsString('secret', json_encode($invalid, JSON_THROW_ON_ERROR));
    }

    public function testPlaybackStallsAndResourceTimingsAreBoundedWithoutLeakingResourceNames(): void
    {
        $service = new CourseMediaDiagnosticsService();
        $session = $service->createSessionId();
        $timings = [];

        for ($index = 0; $index < 55; $index++) {
            $timings[] = [
                'start_time' => $index + 0.125,
                'duration' => $index === 54 ? INF : 25.5,
                'response_start' => 10.25,
                'response_end' => 35.75,
                'response_status' => 206,
                'transfer_size' => 1024,
                'encoded_body_size' => 900,
                'decoded_body_size' => 900,
                'name' => 'https://example.test/private.mp4?nonce=secret',
                'initiator_type' => 'video-secret',
            ];
        }

        $report = $service->report(9, $session, 'course', 'lesson', [
            [
                'event' => 'playback-stall-start',
                'playback_stall_open' => true,
                'playback_stall_duration_ms' => 3000,
            ],
            [
                'event' => 'playback-stall-end',
                'playback_stall_duration_ms' => 4500,
                'playback_stall_end_reason' => 'pause',
            ],
            [
                'event' => 'diagnostics-export',
                'playback_stall_open' => 'secret',
                'playback_stall_duration_ms' => 999999999,
                'playback_stall_end_reason' => 'https://example.test/private',
            ],
            [
                'event' => 'playback-stall-end',
                'playback_stall_duration_ms' => 5000,
                'playback_stall_end_reason' => 'error',
            ],
        ], [
            'playback_stall_threshold_ms' => 3000,
            'diagnostics_performance_origin_ms' => 1234.567,
            'media_resource_timings' => $timings,
        ], 'test');
        $encoded = json_encode($report, JSON_THROW_ON_ERROR);

        self::assertTrue($report['client_events'][0]['playback_stall_open']);
        self::assertSame(3000, $report['client_events'][0]['playback_stall_duration_ms']);
        self::assertSame('pause', $report['client_events'][1]['playback_stall_end_reason']);
        self::assertFalse($report['client_events'][2]['playback_stall_open']);
        self::assertSame(86400000, $report['client_events'][2]['playback_stall_duration_ms']);
        self::assertSame('', $report['client_events'][2]['playback_stall_end_reason']);
        self::assertSame('error', $report['client_events'][3]['playback_stall_end_reason']);
        self::assertSame(3000, $report['environment']['playback_stall_threshold_ms']);
        self::assertSame(1234.567, $report['environment']['diagnostics_performance_origin_ms']);
        self::assertCount(50, $report['environment']['media_resource_timings']);
        self::assertSame(5.125, $report['environment']['media_resource_timings'][0]['start_time']);
        self::assertSame(0.0, $report['environment']['media_resource_timings'][49]['duration']);
        self::assertSame(
            [
                'start_time',
                'duration',
                'response_start',
                'response_end',
                'response_status',
                'transfer_size',
                'encoded_body_size',
                'decoded_body_size',
            ],
            array_keys($report['environment']['media_resource_timings'][0])
        );
        self::assertStringNotContainsString('example.test', $encoded);
        self::assertStringNotContainsString('nonce', $encoded);
        self::assertStringNotContainsString('secret', $encoded);
    }
}
