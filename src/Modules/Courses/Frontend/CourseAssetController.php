<?php

namespace AMToolkit\Modules\Courses\Frontend;

use AMToolkit\Modules\Courses\Contracts\CourseAssetStore;
use AMToolkit\Modules\Courses\Domain\HttpByteRange;
use AMToolkit\Modules\Courses\Domain\ProtectedAsset;
use AMToolkit\Modules\Courses\Services\CourseCatalogService;

defined('ABSPATH') || exit;

final class CourseAssetController
{
    private const ACTION = 'am_toolkit_course_asset';
    private const CHUNK_SIZE = 1048576;

    /** @var array<string, CourseAssetStore> */
    private array $stores = [];

    /** @param list<CourseAssetStore> $stores */
    public function __construct(private CourseCatalogService $courses, array $stores)
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
        string $assetPublicId = ''
    ): string {
        $arguments = [
            'action' => self::ACTION,
            'course' => $coursePublicId,
            'lesson' => $lessonPublicId,
            'kind' => $kind,
            'asset' => $assetPublicId,
        ];
        $arguments['_wpnonce'] = wp_create_nonce($this->nonceAction(
            get_current_user_id(),
            $coursePublicId,
            $lessonPublicId,
            $kind,
            $assetPublicId
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
        $nonce = $this->requestValue('_wpnonce');
        $userId = get_current_user_id();

        if (
            !in_array($kind, ['video', 'material'], true)
            || !wp_verify_nonce(
                $nonce,
                $this->nonceAction($userId, $coursePublicId, $lessonPublicId, $kind, $assetPublicId)
            )
        ) {
            $this->notFound();
        }

        $assetData = $this->courses->assetForUser(
            $userId,
            $coursePublicId,
            $lessonPublicId,
            $kind,
            $assetPublicId
        );

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
            $method
        );
    }

    public function notFound(): void
    {
        status_header(404);
        nocache_headers();
        exit;
    }

    private function serve(ProtectedAsset $asset, string $disposition, string $method): void
    {
        $rangeHeader = isset($_SERVER['HTTP_RANGE']) && is_string($_SERVER['HTTP_RANGE'])
            ? $_SERVER['HTTP_RANGE']
            : null;
        $range = HttpByteRange::fromHeader($rangeHeader, $asset->size());

        if (is_wp_error($range)) {
            status_header(416);
            header('Content-Range: bytes */' . $asset->size());
            header('Content-Length: 0');
            exit;
        }

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        nocache_headers();
        status_header($range->isPartial() ? 206 : 200);
        header('Accept-Ranges: bytes');
        header('Content-Type: ' . $asset->mimeType());
        header('Content-Length: ' . $range->length());
        header('X-Content-Type-Options: nosniff');

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
            exit;
        }

        $stream = fopen($asset->path(), 'rb');

        if ($stream === false || fseek($stream, $range->start()) !== 0) {
            status_header(500);
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
        string $assetPublicId
    ): string {
        return implode(':', [
            self::ACTION,
            $userId,
            $coursePublicId,
            $lessonPublicId,
            $kind,
            $assetPublicId,
        ]);
    }
}
