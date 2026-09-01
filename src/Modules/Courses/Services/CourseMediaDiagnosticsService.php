<?php

namespace AMToolkit\Modules\Courses\Services;

defined('ABSPATH') || exit;

final class CourseMediaDiagnosticsService
{
    public const QUERY_FLAG = 'am_course_diagnostics';

    private const SESSION_PREFIX = 'AMD-';
    private const MAX_SERVER_EVENTS = 120;
    private const MAX_CLIENT_EVENTS = 250;
    private const TRANSIENT_TTL = 1800;

    public function createSessionId(): string
    {
        return self::SESSION_PREFIX . strtoupper(bin2hex(random_bytes(12)));
    }

    public function createRequestId(): string
    {
        return 'AMR-' . strtoupper(bin2hex(random_bytes(8)));
    }

    public function isValidSessionId(string $sessionId): bool
    {
        return preg_match('/^' . self::SESSION_PREFIX . '[A-F0-9]{24}$/', $sessionId) === 1;
    }

    /** @param array<string, mixed> $event */
    public function recordRange(int $userId, string $sessionId, array $event): void
    {
        if ($userId < 1 || !$this->isValidSessionId($sessionId)) {
            return;
        }

        $stored = get_transient($this->transientKey($userId, $sessionId));
        $events = is_array($stored) ? array_values($stored) : [];
        $events[] = $this->normalizeServerEvent($event);
        $events = array_slice($events, -self::MAX_SERVER_EVENTS);

        set_transient(
            $this->transientKey($userId, $sessionId),
            $events,
            self::TRANSIENT_TTL
        );
    }

    /**
     * @param list<mixed> $clientEvents
     * @param array<string, mixed> $environment
     * @return array<string, mixed>
     */
    public function report(
        int $userId,
        string $sessionId,
        string $coursePublicId,
        string $lessonPublicId,
        array $clientEvents,
        array $environment,
        string $pluginVersion
    ): array {
        $normalizedEvents = [];

        foreach (array_slice($clientEvents, -self::MAX_CLIENT_EVENTS) as $event) {
            if (!is_array($event)) {
                continue;
            }

            $normalizedEvents[] = $this->normalizeClientEvent($event);
        }

        $stored = $this->isValidSessionId($sessionId)
            ? get_transient($this->transientKey($userId, $sessionId))
            : [];

        return [
            'format_version' => 1,
            'generated_at_utc' => gmdate('c'),
            'plugin_version' => sanitize_text_field($pluginVersion),
            'diagnostic_session' => $this->isValidSessionId($sessionId) ? $sessionId : '',
            'user_ref' => $this->reference('user:' . $userId),
            'course_ref' => $this->reference('course:' . $coursePublicId),
            'lesson_ref' => $this->reference('lesson:' . $lessonPublicId),
            'environment' => $this->normalizeEnvironment($environment),
            'client_events' => $normalizedEvents,
            'server_range_requests' => is_array($stored) ? array_values($stored) : [],
            'privacy' => [
                'contains_credentials' => false,
                'contains_source_urls' => false,
                'contains_ip_address' => false,
            ],
        ];
    }

    /** @param array<string, mixed> $event @return array<string, mixed> */
    private function normalizeServerEvent(array $event): array
    {
        return [
            'request_id' => $this->shortText($event['request_id'] ?? '', 40),
            'recorded_at_utc' => $this->shortText($event['recorded_at_utc'] ?? gmdate('c'), 40),
            'phase' => $this->allowedText($event['phase'] ?? '', ['start', 'end']),
            'method' => $this->allowedText($event['method'] ?? '', ['GET', 'HEAD']),
            'status' => $this->boundedInt($event['status'] ?? 0, 0, 599),
            'partial' => !empty($event['partial']),
            'range_header_present' => !empty($event['range_header_present']),
            'range_header_source' => $this->allowedText(
                $event['range_header_source'] ?? '',
                ['http_range', 'redirect_http_range', 'headers', 'missing']
            ),
            'range_start' => $this->nonNegativeInt($event['range_start'] ?? 0),
            'range_end' => $this->nonNegativeInt($event['range_end'] ?? 0),
            'range_length' => $this->nonNegativeInt($event['range_length'] ?? 0),
            'resource_size' => $this->nonNegativeInt($event['resource_size'] ?? 0),
            'bytes_sent' => $this->nonNegativeInt($event['bytes_sent'] ?? 0),
            'duration_ms' => $this->nonNegativeInt($event['duration_ms'] ?? 0),
            'connection_status' => $this->nonNegativeInt($event['connection_status'] ?? 0),
            'completed' => !empty($event['completed']),
            'aborted' => !empty($event['aborted']),
        ];
    }

    /** @param array<string, mixed> $event @return array<string, mixed> */
    private function normalizeClientEvent(array $event): array
    {
        return [
            'event' => $this->shortText($event['event'] ?? '', 40),
            'recorded_at_utc' => $this->shortText($event['recorded_at_utc'] ?? '', 40),
            'at_ms' => $this->boundedInt($event['at_ms'] ?? 0, 0, 86400000),
            'current_time' => $this->boundedFloat($event['current_time'] ?? 0, 0, 86400),
            'duration' => $this->boundedFloat($event['duration'] ?? 0, 0, 86400),
            'paused' => !empty($event['paused']),
            'ended' => !empty($event['ended']),
            'ready_state' => $this->boundedInt($event['ready_state'] ?? 0, 0, 4),
            'network_state' => $this->boundedInt($event['network_state'] ?? 0, 0, 3),
            'playback_rate' => $this->boundedFloat($event['playback_rate'] ?? 1, 0, 16),
            'buffered' => $this->normalizeRanges($event['buffered'] ?? []),
            'seekable' => $this->normalizeRanges($event['seekable'] ?? []),
            'error_code' => $this->boundedInt($event['error_code'] ?? 0, 0, 9),
            'visibility' => $this->allowedText(
                $event['visibility'] ?? '',
                ['visible', 'hidden', 'prerender', 'unloaded']
            ),
            'online' => !isset($event['online']) || !empty($event['online']),
        ];
    }

    /** @param array<string, mixed> $environment @return array<string, mixed> */
    private function normalizeEnvironment(array $environment): array
    {
        return [
            'user_agent' => $this->shortText($environment['user_agent'] ?? '', 512),
            'platform' => $this->shortText($environment['platform'] ?? '', 80),
            'language' => $this->shortText($environment['language'] ?? '', 20),
            'viewport_width' => $this->boundedInt($environment['viewport_width'] ?? 0, 0, 10000),
            'viewport_height' => $this->boundedInt($environment['viewport_height'] ?? 0, 0, 10000),
            'device_pixel_ratio' => $this->boundedFloat($environment['device_pixel_ratio'] ?? 1, 0, 10),
            'connection_effective_type' => $this->allowedText(
                $environment['connection_effective_type'] ?? '',
                ['', 'slow-2g', '2g', '3g', '4g']
            ),
        ];
    }

    /** @param mixed $ranges @return list<array{0: float, 1: float}> */
    private function normalizeRanges(mixed $ranges): array
    {
        if (!is_array($ranges)) {
            return [];
        }

        $normalized = [];

        foreach (array_slice($ranges, 0, 20) as $range) {
            if (!is_array($range) || count($range) < 2) {
                continue;
            }

            $start = $this->boundedFloat($range[0] ?? 0, 0, 86400);
            $end = $this->boundedFloat($range[1] ?? 0, $start, 86400);
            $normalized[] = [$start, $end];
        }

        return $normalized;
    }

    private function transientKey(int $userId, string $sessionId): string
    {
        return 'amt_media_diag_' . substr(
            hash_hmac('sha256', $userId . '|' . $sessionId, wp_salt('nonce')),
            0,
            32
        );
    }

    private function reference(string $value): string
    {
        return substr(hash_hmac('sha256', $value, wp_salt('nonce')), 0, 16);
    }

    private function shortText(mixed $value, int $maxLength): string
    {
        $value = is_scalar($value) ? sanitize_text_field((string) $value) : '';

        return function_exists('mb_substr')
            ? mb_substr($value, 0, $maxLength)
            : substr($value, 0, $maxLength);
    }

    /** @param list<string> $allowed */
    private function allowedText(mixed $value, array $allowed): string
    {
        $value = is_scalar($value) ? (string) $value : '';

        return in_array($value, $allowed, true) ? $value : '';
    }

    private function nonNegativeInt(mixed $value): int
    {
        return max(0, (int) $value);
    }

    private function boundedInt(mixed $value, int $minimum, int $maximum): int
    {
        return max($minimum, min($maximum, (int) $value));
    }

    private function boundedFloat(mixed $value, float $minimum, float $maximum): float
    {
        $number = is_numeric($value) ? (float) $value : $minimum;

        if (!is_finite($number)) {
            return $minimum;
        }

        return round(max($minimum, min($maximum, $number)), 3);
    }
}
