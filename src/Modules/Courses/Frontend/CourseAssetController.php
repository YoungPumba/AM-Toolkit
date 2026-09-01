<?php

namespace AMToolkit\Modules\Courses\Frontend;

use AMToolkit\Core\Authorization;
use AMToolkit\Modules\Courses\Contracts\CourseAssetStore;
use AMToolkit\Modules\Courses\Domain\HttpByteRange;
use AMToolkit\Modules\Courses\Domain\ProtectedAsset;
use AMToolkit\Modules\Courses\Services\CourseCatalogService;
use AMToolkit\Modules\Courses\Services\CourseMediaDiagnosticsService;
use AMToolkit\Modules\Courses\Services\CoursePreviewService;

defined('ABSPATH') || exit;

final class CourseAssetController
{
    private const ACTION = 'am_toolkit_course_asset';
    private const CHUNK_SIZE = 1048576;
    private const VIDEO_OPEN_RANGE_LENGTH = 134217728;

    /** @var array<string, CourseAssetStore> */
    private array $stores = [];

    /** @param list<CourseAssetStore> $stores */
    public function __construct(
        private CourseCatalogService $courses,
        array $stores,
        private ?CoursePreviewService $preview = null,
        private ?CourseMediaDiagnosticsService $mediaDiagnostics = null
    )
    {
        foreach ($stores as $store) {
            $this->stores[$store->provider()] = $store;
        }
    }

    public function boot(): void
    {
        add_action('admin_post_' . self::ACTION, [$this, 'handle']);
        add_action('admin_post_nopriv_' . self::ACTION, [$this, 'notFound']);
    }

    public function url(
        string $coursePublicId,
        string $lessonPublicId,
        string $kind,
        string $assetPublicId = '',
        int $previewCourseId = 0,
        string $diagnosticSessionId = ''
    ): string {
        $arguments = [
            'action' => self::ACTION,
            'course' => $coursePublicId,
            'lesson' => $lessonPublicId,
            'kind' => $kind,
            'asset' => $assetPublicId,
        ];
        if ($previewCourseId > 0) {
            $arguments['preview'] = $previewCourseId;
        }
        if (
            $kind === 'video'
            && $this->mediaDiagnostics !== null
            && $this->mediaDiagnostics->isValidSessionId($diagnosticSessionId)
        ) {
            $arguments['diagnostic'] = $diagnosticSessionId;
        }
        $arguments['_wpnonce'] = wp_create_nonce($this->nonceAction(
            get_current_user_id(),
            $coursePublicId,
            $lessonPublicId,
            $kind,
            $assetPublicId,
            $previewCourseId,
            isset($arguments['diagnostic']) ? (string) $arguments['diagnostic'] : ''
        ));

        return add_query_arg($arguments, admin_url('admin-post.php'));
    }

    public function handle(): void
    {
        $method = isset($_SERVER['REQUEST_METHOD']) && is_string($_SERVER['REQUEST_METHOD'])
            ? strtoupper($_SERVER['REQUEST_METHOD'])
            : 'GET';

        if (!in_array($method, ['GET', 'HEAD'], true) || !is_user_logged_in()) {
            $this->notFound();
        }

        $coursePublicId = $this->requestValue('course');
        $lessonPublicId = $this->requestValue('lesson');
        $kind = sanitize_key($this->requestValue('kind'));
        $assetPublicId = $this->requestValue('asset');
        $previewCourseId = absint($this->requestValue('preview'));
        $diagnosticSessionId = $this->requestValue('diagnostic');
        $nonce = $this->requestValue('_wpnonce');
        $userId = get_current_user_id();

        if (
            !in_array($kind, ['video', 'material'], true)
            || !wp_verify_nonce(
                $nonce,
                $this->nonceAction(
                    $userId,
                    $coursePublicId,
                    $lessonPublicId,
                    $kind,
                    $assetPublicId,
                    $previewCourseId,
                    $diagnosticSessionId
                )
            )
        ) {
            $this->notFound();
        }

        if ($previewCourseId > 0) {
            if ($this->preview === null || !Authorization::canManageCourses()) {
                $this->notFound();
            }
            $previewCourse = $this->preview->course($previewCourseId);
            if (
                is_wp_error($previewCourse)
                || (string) ($previewCourse['public_id'] ?? '') !== $coursePublicId
            ) {
                $this->notFound();
            }
            $assetData = $this->preview->asset(
                $previewCourseId,
                $lessonPublicId,
                $kind,
                $assetPublicId
            );
        } else {
            $assetData = $this->courses->assetForUser(
                $userId,
                $coursePublicId,
                $lessonPublicId,
                $kind,
                $assetPublicId
            );
        }

        if (is_wp_error($assetData)) {
            $this->notFound();
        }

        $provider = (string) ($assetData['provider'] ?? '');
        $store = $this->stores[$provider] ?? null;

        if ($store === null) {
            $this->notFound();
        }

        $asset = $store->locate(
            (string) ($assetData['reference'] ?? ''),
            (string) ($assetData['name'] ?? __('Materiał kursu', 'am-toolkit'))
        );

        if (is_wp_error($asset)) {
            $this->notFound();
        }

        $this->serve(
            $asset,
            (string) ($assetData['disposition'] ?? 'attachment'),
            $method,
            $kind === 'video' ? self::VIDEO_OPEN_RANGE_LENGTH : null,
            $kind === 'video' ? $userId : 0,
            $kind === 'video' ? $diagnosticSessionId : ''
        );
    }

    public function notFound(): void
    {
        status_header(404);
        nocache_headers();
        exit;
    }

    private function serve(
        ProtectedAsset $asset,
        string $disposition,
        string $method,
        ?int $maxOpenEndedRangeLength = null,
        int $diagnosticUserId = 0,
        string $diagnosticSessionId = ''
    ): void
    {
        $rangeHeader = isset($_SERVER['HTTP_RANGE']) && is_string($_SERVER['HTTP_RANGE'])
            ? $_SERVER['HTTP_RANGE']
            : null;
        $range = HttpByteRange::fromHeader(
            $rangeHeader,
            $asset->size(),
            $maxOpenEndedRangeLength
        );

        if (is_wp_error($range)) {
            status_header(416);
            header('Content-Range: bytes */' . $asset->size());
            header('Content-Length: 0');
            exit;
        }

        $diagnosticEvent = null;
        $diagnosticStartedAt = microtime(true);

        if (
            $this->mediaDiagnostics !== null
            && $diagnosticUserId > 0
            && $this->mediaDiagnostics->isValidSessionId($diagnosticSessionId)
        ) {
            $diagnosticEvent = [
                'request_id' => $this->mediaDiagnostics->createRequestId(),
                'recorded_at_utc' => gmdate('c'),
                'phase' => 'start',
                'method' => $method,
                'status' => $range->isPartial() ? 206 : 200,
                'partial' => $range->isPartial(),
                'range_start' => $range->start(),
                'range_end' => $range->end(),
                'range_length' => $range->length(),
                'resource_size' => $range->resourceSize(),
                'bytes_sent' => 0,
                'duration_ms' => 0,
                'connection_status' => CONNECTION_NORMAL,
                'completed' => false,
                'aborted' => false,
            ];
            $diagnostics = $this->mediaDiagnostics;
            $diagnostics->recordRange($diagnosticUserId, $diagnosticSessionId, $diagnosticEvent);

            register_shutdown_function(static function () use (
                $diagnostics,
                $diagnosticUserId,
                $diagnosticSessionId,
                &$diagnosticEvent,
                $diagnosticStartedAt
            ): void {
                $connectionStatus = connection_status();
                $diagnosticEvent['phase'] = 'end';
                $diagnosticEvent['recorded_at_utc'] = gmdate('c');
                $diagnosticEvent['duration_ms'] = max(
                    0,
                    (int) round((microtime(true) - $diagnosticStartedAt) * 1000)
                );
                $diagnosticEvent['connection_status'] = $connectionStatus;
                $diagnosticEvent['aborted'] = ($connectionStatus & CONNECTION_ABORTED) === CONNECTION_ABORTED;
                $diagnosticEvent['completed'] = $diagnosticEvent['method'] === 'HEAD'
                    || (
                        (int) $diagnosticEvent['bytes_sent'] >= (int) $diagnosticEvent['range_length']
                        && !$diagnosticEvent['aborted']
                    );
                $diagnostics->recordRange($diagnosticUserId, $diagnosticSessionId, $diagnosticEvent);
            });
        }

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        if (function_exists('set_time_limit')) {
            @set_time_limit(0); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
        }

        @ini_set('zlib.output_compression', '0'); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
        header_remove('Content-Encoding');

        nocache_headers();
        header_remove('Expires');
        header_remove('Pragma');
        status_header($range->isPartial() ? 206 : 200);
        $modifiedAt = filemtime($asset->path());
        $entityTag = sprintf('"amt-%s"', hash('sha256', $asset->path() . '|' . $asset->size() . '|' . (string) $modifiedAt));
        header('Accept-Ranges: bytes');
        header('Content-Type: ' . $asset->mimeType());
        header('Content-Length: ' . $range->length());
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, max-age=3600, no-transform');
        header('Vary: Cookie');
        header('ETag: ' . $entityTag);

        if ($modifiedAt !== false) {
            header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $modifiedAt) . ' GMT');
        }

        if ($range->isPartial()) {
            header(sprintf(
                'Content-Range: bytes %d-%d/%d',
                $range->start(),
                $range->end(),
                $range->resourceSize()
            ));
        }

        $safeDisposition = $disposition === 'inline' ? 'inline' : 'attachment';
        $asciiName = preg_replace('/[^A-Za-z0-9._-]/', '_', $asset->downloadName()) ?: 'material';
        header(sprintf(
            "Content-Disposition: %s; filename=\"%s\"; filename*=UTF-8''%s",
            $safeDisposition,
            $asciiName,
            rawurlencode($asset->downloadName())
        ));

        if ($method === 'HEAD' || $range->length() === 0) {
            if (is_array($diagnosticEvent)) {
                $diagnosticEvent['completed'] = true;
            }
            exit;
        }

        $stream = fopen($asset->path(), 'rb');

        if ($stream === false || fseek($stream, $range->start()) !== 0) {
            status_header(500);
            if (is_array($diagnosticEvent)) {
                $diagnosticEvent['status'] = 500;
            }
            exit;
        }

        $remaining = $range->length();

        while ($remaining > 0 && !feof($stream) && connection_status() === CONNECTION_NORMAL) {
            $buffer = fread($stream, min(self::CHUNK_SIZE, $remaining));

            if ($buffer === false || $buffer === '') {
                break;
            }

            echo $buffer; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            $remaining -= strlen($buffer);
            if (is_array($diagnosticEvent)) {
                $diagnosticEvent['bytes_sent'] = (int) $diagnosticEvent['bytes_sent'] + strlen($buffer);
            }
            flush();
        }

        fclose($stream);
        exit;
    }

    private function requestValue(string $key): string
    {
        if (!isset($_GET[$key]) || !is_scalar($_GET[$key])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            return '';
        }

        return sanitize_text_field((string) wp_unslash($_GET[$key])); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    }

    private function nonceAction(
        int $userId,
        string $coursePublicId,
        string $lessonPublicId,
        string $kind,
        string $assetPublicId,
        int $previewCourseId = 0,
        string $diagnosticSessionId = ''
    ): string {
        return implode(':', [
            self::ACTION,
            $userId,
            $coursePublicId,
            $lessonPublicId,
            $kind,
            $assetPublicId,
            $previewCourseId,
            $diagnosticSessionId,
        ]);
    }
}
