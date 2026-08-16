<?php

namespace AMToolkit\Modules\Courses;

use AMToolkit\Modules\Courses\Contracts\CourseAssetStore;
use AMToolkit\Modules\Courses\Domain\ProtectedAsset;

defined('ABSPATH') || exit;

final class WpPrivateCourseAssetStore implements CourseAssetStore
{
    public const PROVIDER = 'am-private';

    /** @var array<string, string> */
    private const MIME_TYPES = [
        'mp4' => 'video/mp4',
        'pdf' => 'application/pdf',
        'zip' => 'application/zip',
        'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xls' => 'application/vnd.ms-excel',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'ppt' => 'application/vnd.ms-powerpoint',
        'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'csv' => 'text/csv',
        'txt' => 'text/plain',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'webp' => 'image/webp',
    ];

    public function __construct(private ?string $configuredBasePath = null)
    {
    }

    public function provider(): string
    {
        return self::PROVIDER;
    }

    public function basePath(): string
    {
        if ($this->configuredBasePath !== null && trim($this->configuredBasePath) !== '') {
            return rtrim($this->configuredBasePath, '/\\');
        }

        $default = dirname(rtrim(ABSPATH, '/\\')) . DIRECTORY_SEPARATOR . 'am-toolkit-private';
        $constant = defined('AM_TOOLKIT_PRIVATE_STORAGE_PATH')
            ? (string) constant('AM_TOOLKIT_PRIVATE_STORAGE_PATH')
            : $default;

        return rtrim((string) apply_filters('am_toolkit_private_storage_path', $constant), '/\\');
    }

    public function storeUpload(array $upload, string $kind): string|\WP_Error
    {
        $kindDirectory = $kind === 'video' ? 'videos' : ($kind === 'material' ? 'materials' : '');
        $error = isset($upload['error']) ? (int) $upload['error'] : UPLOAD_ERR_NO_FILE;

        if ($kindDirectory === '' || $error !== UPLOAD_ERR_OK) {
            return new \WP_Error(
                'am_toolkit_course_asset_upload_failed',
                $error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE
                    ? __('Plik przekracza limit wysyłania ustawiony na hostingu.', 'am-toolkit')
                    : __('Nie udało się odebrać przesłanego pliku.', 'am-toolkit')
            );
        }

        $temporaryPath = isset($upload['tmp_name']) && is_string($upload['tmp_name'])
            ? $upload['tmp_name']
            : '';
        $originalName = isset($upload['name']) && is_string($upload['name'])
            ? $upload['name']
            : '';
        $extension = strtolower((string) pathinfo($originalName, PATHINFO_EXTENSION));
        $allowed = $kind === 'video' ? ['mp4'] : array_keys(self::MIME_TYPES);

        if (
            $temporaryPath === ''
            || !is_uploaded_file($temporaryPath)
            || !in_array($extension, $allowed, true)
        ) {
            return new \WP_Error(
                'am_toolkit_course_asset_type_not_allowed',
                $kind === 'video'
                    ? __('Nagranie lekcji musi być plikiem MP4.', 'am-toolkit')
                    : __('Ten typ materiału nie jest obsługiwany.', 'am-toolkit')
            );
        }

        $directory = $this->basePath() . DIRECTORY_SEPARATOR . $kindDirectory;

        if (!wp_mkdir_p($directory) || !is_dir($directory) || !is_writable($directory)) {
            return new \WP_Error(
                'am_toolkit_private_storage_unavailable',
                __('Prywatny katalog kursów nie jest dostępny do zapisu.', 'am-toolkit')
            );
        }

        $reference = $kindDirectory . '/' . wp_generate_uuid4() . '.' . $extension;
        $target = $this->basePath() . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $reference);

        if (!move_uploaded_file($temporaryPath, $target)) {
            return new \WP_Error(
                'am_toolkit_course_asset_move_failed',
                __('Nie udało się zapisać pliku w prywatnym magazynie.', 'am-toolkit')
            );
        }

        return $reference;
    }

    public function locate(string $reference, string $downloadName): ProtectedAsset|\WP_Error
    {
        if (!preg_match('#^(videos|materials)/[a-f0-9-]{36}\.([a-z0-9]{1,10})$#', $reference, $matches)) {
            return $this->notFound();
        }

        $extension = strtolower($matches[2]);
        $mimeType = self::MIME_TYPES[$extension] ?? '';

        if ($mimeType === '') {
            return $this->notFound();
        }

        $base = realpath($this->basePath());
        $path = realpath($this->basePath() . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $reference));

        if (
            $base === false
            || $path === false
            || !is_file($path)
            || !str_starts_with($path, $base . DIRECTORY_SEPARATOR)
        ) {
            return $this->notFound();
        }

        $size = filesize($path);

        if ($size === false) {
            return $this->notFound();
        }

        $safeName = sanitize_file_name($downloadName);

        if ($safeName === '') {
            $safeName = 'material';
        }

        if (strtolower((string) pathinfo($safeName, PATHINFO_EXTENSION)) !== $extension) {
            $safeName .= '.' . $extension;
        }

        return new ProtectedAsset($path, $mimeType, $safeName, (int) $size);
    }

    public function videoDurationSeconds(string $reference): int|\WP_Error
    {
        $asset = $this->locate($reference, 'video.mp4');

        if (is_wp_error($asset)) {
            return $asset;
        }

        if (!function_exists('wp_read_video_metadata')) {
            $mediaFile = ABSPATH . 'wp-admin/includes/media.php';

            if (!is_file($mediaFile)) {
                return $this->metadataError();
            }

            require_once $mediaFile;
        }

        $metadata = wp_read_video_metadata($asset->path());
        $duration = is_array($metadata) && is_numeric($metadata['length'] ?? null)
            ? (int) round((float) $metadata['length'])
            : 0;

        return $duration > 0 ? $duration : $this->metadataError();
    }

    public function remove(string $reference): bool
    {
        $asset = $this->locate($reference, 'asset');

        return !is_wp_error($asset) && unlink($asset->path());
    }

    private function notFound(): \WP_Error
    {
        return new \WP_Error(
            'am_toolkit_course_asset_not_found',
            __('Plik kursu jest niedostępny.', 'am-toolkit')
        );
    }

    private function metadataError(): \WP_Error
    {
        return new \WP_Error(
            'am_toolkit_course_video_metadata_unavailable',
            __('Nie udało się odczytać czasu nagrania.', 'am-toolkit')
        );
    }
}
