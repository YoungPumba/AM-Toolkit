<?php

declare(strict_types=1);

use AMToolkit\Modules\Courses\CoursesSchema;
use AMToolkit\Modules\Courses\Domain\Mp4StreamabilityInspector;
use AMToolkit\Modules\Courses\WpPrivateCourseAssetStore;

const AMT_VIDEO_REFERENCE_PATTERN = '#^videos/[a-f0-9-]{36}\.mp4$#';

/**
 * This script deliberately accepts only a WordPress installation managed by
 * Local and a loopback database endpoint. It is an inventory tool, not a
 * production diagnostic endpoint.
 */
function amt_is_local_wordpress_path(string $path): bool
{
    if (str_contains($path, '://') || str_starts_with($path, '\\\\') || str_starts_with($path, '//')) {
        return false;
    }

    $realPath = realpath($path);

    if ($realPath === false || !is_file($realPath)) {
        return false;
    }

    $normalized = str_replace('\\', '/', $realPath);

    return preg_match('#(?:^|/)Local Sites/[^/]+/app/public/wp-load\.php$#i', $normalized) === 1;
}

function amt_is_loopback_database_host(string $databaseHost): bool
{
    if (preg_match('/^(?:localhost|127\.0\.0\.1):(\d{1,5})$/i', $databaseHost, $matches) === 1) {
        $port = (int) $matches[1];

        return $port > 0 && $port <= 65535;
    }

    if (preg_match('/^\[::1\]:(\d{1,5})$/', $databaseHost, $matches) === 1) {
        $port = (int) $matches[1];

        return $port > 0 && $port <= 65535;
    }

    return false;
}

function amt_is_local_site_url(string $url): bool
{
    $host = strtolower((string) parse_url($url, PHP_URL_HOST));

    return $host === 'localhost'
        || $host === '127.0.0.1'
        || $host === '::1'
        || str_ends_with($host, '.local');
}

function amt_is_local_storage_path(string $path): bool
{
    if ($path === '' || str_contains($path, '://') || str_starts_with($path, '\\\\') || str_starts_with($path, '//')) {
        return false;
    }

    $siteRoot = realpath(dirname(dirname(rtrim(ABSPATH, '/\\'))));
    $resolvedPath = realpath($path);

    if ($resolvedPath === false) {
        $resolvedParent = realpath(dirname($path));

        if ($resolvedParent === false) {
            return false;
        }

        $resolvedPath = $resolvedParent . DIRECTORY_SEPARATOR . basename($path);
    }

    if ($siteRoot === false) {
        return false;
    }

    $normalizedRoot = strtolower(str_replace('\\', '/', rtrim($siteRoot, '/\\')) . '/');
    $normalizedPath = strtolower(str_replace('\\', '/', rtrim($resolvedPath, '/\\')) . '/');

    return str_starts_with($normalizedPath, $normalizedRoot);
}

function amt_safe_asset_ref(string $provider, string $reference): string
{
    return substr(hash('sha256', $provider . "\0" . $reference), 0, 24);
}

function amt_safe_provider_label(string $provider): string
{
    return $provider === WpPrivateCourseAssetStore::PROVIDER
        ? WpPrivateCourseAssetStore::PROVIDER
        : 'external_or_unknown';
}

/**
 * @return array{storage_state: string, size_bytes: int, fast_start: string, sha256?: string}
 */
function amt_inspect_private_video(
    string $reference,
    WpPrivateCourseAssetStore $store,
    Mp4StreamabilityInspector $inspector,
    bool $hashFiles
): array {
    $asset = $store->locate($reference, 'video.mp4');

    if (is_wp_error($asset)) {
        throw new RuntimeException('Private asset could not be resolved safely.');
    }

    set_error_handler(static fn (): bool => true);

    try {
        $streamability = $inspector->inspect($asset->path());
        $fastStart = is_wp_error($streamability)
            ? 'invalid'
            : ($streamability ? 'yes' : 'no');

        $result = [
            'storage_state' => 'present',
            'size_bytes' => $asset->size(),
            'fast_start' => $fastStart,
        ];

        if ($hashFiles) {
            // hash_file() streams the file; it does not load a video into memory.
            $checksum = hash_file('sha256', $asset->path());

            if (is_string($checksum) && $checksum !== '') {
                $result['sha256'] = $checksum;
            }
        }
    } finally {
        restore_error_handler();
    }

    return $result;
}

/**
 * @return array<string, array{storage_state: string, size_bytes: int|null, fast_start: string, sha256?: string}>
 */
function amt_private_video_files(
    WpPrivateCourseAssetStore $store,
    Mp4StreamabilityInspector $inspector,
    bool $hashFiles,
    int &$ignoredEntries
): array {
    $directory = $store->basePath() . DIRECTORY_SEPARATOR . 'videos';
    $videos = [];
    $ignoredEntries = 0;

    if (!is_dir($directory)) {
        return [];
    }

    $iterator = new FilesystemIterator($directory, FilesystemIterator::SKIP_DOTS);

    foreach ($iterator as $file) {
        $filename = $file->getFilename();
        $reference = 'videos/' . $filename;

        if (preg_match(AMT_VIDEO_REFERENCE_PATTERN, $reference) !== 1) {
            $ignoredEntries++;
            continue;
        }

        if ($file->isLink() || !$file->isFile()) {
            $videos[$reference] = [
                'storage_state' => 'unreadable',
                'size_bytes' => null,
                'fast_start' => 'unreadable',
            ];
            continue;
        }

        try {
            $videos[$reference] = amt_inspect_private_video($reference, $store, $inspector, $hashFiles);
        } catch (Throwable) {
            // The entry exists but failed the storage contract's containment or
            // readability checks. Count it without exposing its filename/path.
            $videos[$reference] = [
                'storage_state' => 'unreadable',
                'size_bytes' => null,
                'fast_start' => 'unreadable',
            ];
        }
    }

    return $videos;
}

function amt_usage_count(array $row, string $key): int
{
    return isset($row[$key]) && is_numeric($row[$key]) ? (int) $row[$key] : 0;
}

function amt_write_usage(): void
{
    fwrite(
        STDERR,
        "Usage: php .build/inspect-course-videos-local.php <wp-load.php> <database-host:port> [--hash]\n"
    );
    exit(2);
}

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This audit is available from CLI only.\n");
    exit(2);
}

$wpLoadArgument = $argv[1] ?? '';
$databaseHost = $argv[2] ?? '';
$options = array_slice($argv, 3);

if (
    $wpLoadArgument === ''
    || $databaseHost === ''
    || count(array_diff($options, ['--hash'])) > 0
    || count($options) !== count(array_unique($options))
) {
    amt_write_usage();
}

if (!amt_is_local_wordpress_path($wpLoadArgument)) {
    fwrite(STDERR, "Refusing to bootstrap: wp-load.php must be a Local app/public installation.\n");
    exit(2);
}

if (!amt_is_loopback_database_host($databaseHost)) {
    fwrite(STDERR, "Refusing to bootstrap: the database endpoint must be loopback with an explicit port.\n");
    exit(2);
}

$wpLoad = realpath($wpLoadArgument);

if ($wpLoad === false) {
    fwrite(STDERR, "Refusing to bootstrap: the local WordPress path could not be resolved.\n");
    exit(2);
}

$hashFiles = in_array('--hash', $options, true);

define('SHORTINIT', true);
define('DB_HOST', $databaseHost);

set_error_handler(static function (int $severity, string $message): bool {
    return $severity === E_WARNING && str_contains($message, 'Constant DB_HOST already defined');
});
require $wpLoad;
restore_error_handler();

if (defined('WP_ENVIRONMENT_TYPE')) {
    $environment = strtolower((string) constant('WP_ENVIRONMENT_TYPE'));

    if (!in_array($environment, ['local', 'development'], true)) {
        fwrite(STDERR, "Refusing to inspect: WordPress does not declare a local/development environment.\n");
        exit(2);
    }
}

if (!function_exists('__')) {
    require_once ABSPATH . WPINC . '/l10n.php';
}

if (!class_exists('NOOP_Translations')) {
    require_once ABSPATH . WPINC . '/pomo/translations.php';
}

if (!isset($GLOBALS['l10n']) || !is_array($GLOBALS['l10n'])) {
    $GLOBALS['l10n'] = [];
}

if (!isset($GLOBALS['l10n']['am-toolkit'])) {
    // SHORTINIT has no locale registry. A no-op domain keeps error objects safe
    // without loading translations, options or the rest of WordPress.
    $GLOBALS['l10n']['am-toolkit'] = new NOOP_Translations();
}

if (!function_exists('sanitize_file_name')) {
    require_once ABSPATH . WPINC . '/formatting.php';
}

require_once dirname(__DIR__) . '/vendor/autoload.php';

global $wpdb;

if (!is_object($wpdb) || !method_exists($wpdb, 'get_results')) {
    fwrite(STDERR, "Local WordPress did not provide a database connection.\n");
    exit(1);
}

$wpdb->suppress_errors(true);

try {
    $wpdb->last_error = '';
    $siteRows = $wpdb->get_results(
        "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name IN ('home', 'siteurl')",
        ARRAY_A
    );

    if (!is_array($siteRows) || $wpdb->last_error !== '') {
        throw new RuntimeException('Local site identity query failed.');
    }

    $siteUrls = [];

    foreach ($siteRows as $siteRow) {
        $siteUrls[(string) ($siteRow['option_name'] ?? '')] = (string) ($siteRow['option_value'] ?? '');
    }

    if (
        ($siteUrls['home'] ?? '') === ''
        || ($siteUrls['siteurl'] ?? '') === ''
        || !amt_is_local_site_url($siteUrls['home'])
        || !amt_is_local_site_url($siteUrls['siteurl'])
    ) {
        fwrite(STDERR, "Refusing to inspect: the database does not identify a .local/loopback site.\n");
        exit(2);
    }

    $store = new WpPrivateCourseAssetStore();
    $privateStoragePath = $store->basePath();

    if (!amt_is_local_storage_path($privateStoragePath)) {
        fwrite(STDERR, "Refusing to inspect: private storage is not inside the Local site directory.\n");
        exit(2);
    }

    $store = new WpPrivateCourseAssetStore($privateStoragePath);
    $inspector = new Mp4StreamabilityInspector();

    $lessons = CoursesSchema::lessonsTable();
    $assignments = CoursesSchema::programLessonsTable();
    $programs = CoursesSchema::programVersionsTable();
    $courses = CoursesSchema::coursesTable();

    $wpdb->last_error = '';
    $usageRows = $wpdb->get_results(
        "SELECT
            COALESCE(l.video_provider, '') AS video_provider,
            COALESCE(l.video_reference, '') AS video_reference,
            COUNT(DISTINCT l.id) AS lesson_rows,
            COUNT(DISTINCT pl.id) AS program_assignments,
            COUNT(DISTINCT CASE WHEN pv.status = 'published' THEN pl.id ELSE NULL END) AS published_program_assignments,
            COUNT(DISTINCT CASE WHEN c.current_program_version_id = pl.program_version_id THEN pl.id ELSE NULL END) AS current_program_assignments
        FROM {$lessons} l
        LEFT JOIN {$assignments} pl ON pl.lesson_id = l.id
        LEFT JOIN {$programs} pv ON pv.id = pl.program_version_id
        LEFT JOIN {$courses} c ON c.id = pv.course_id
        WHERE COALESCE(l.video_provider, '') <> '' OR COALESCE(l.video_reference, '') <> ''
        GROUP BY l.video_provider, l.video_reference",
        ARRAY_A
    );

    if (!is_array($usageRows) || $wpdb->last_error !== '') {
        throw new RuntimeException('Course video usage query failed.');
    }

    $ignoredEntries = 0;
    $privateFiles = amt_private_video_files($store, $inspector, $hashFiles, $ignoredEntries);
    $referencedPrivateFiles = [];
    $assets = [];
    $missingAssets = [];
    $duplicateReferences = [];
    $incompleteDatabaseReferences = 0;
    $privateDatabaseReferences = 0;
    $externalDatabaseReferences = 0;
    $publishedReferenceCount = 0;
    $currentReferenceCount = 0;

    foreach ($usageRows as $row) {
        $provider = (string) ($row['video_provider'] ?? '');
        $reference = (string) ($row['video_reference'] ?? '');
        $assetRef = amt_safe_asset_ref($provider, $reference);
        $lessonRows = amt_usage_count($row, 'lesson_rows');
        $programAssignments = amt_usage_count($row, 'program_assignments');
        $publishedAssignments = amt_usage_count($row, 'published_program_assignments');
        $currentAssignments = amt_usage_count($row, 'current_program_assignments');
        $publishedReferenceCount += $publishedAssignments > 0 ? 1 : 0;
        $currentReferenceCount += $currentAssignments > 0 ? 1 : 0;

        $record = [
            'asset_ref' => $assetRef,
            'provider' => amt_safe_provider_label($provider),
            'lesson_rows' => $lessonRows,
            'program_assignments' => $programAssignments,
            'published_program_assignments' => $publishedAssignments,
            'current_program_assignments' => $currentAssignments,
        ];

        if ($provider === '' || $reference === '') {
            $record['storage_state'] = 'incomplete_database_reference';
            $incompleteDatabaseReferences++;
        } elseif ($provider !== WpPrivateCourseAssetStore::PROVIDER) {
            $record['storage_state'] = 'not_managed_by_private_store';
            $externalDatabaseReferences++;
        } elseif (preg_match(AMT_VIDEO_REFERENCE_PATTERN, $reference) !== 1) {
            $record['storage_state'] = 'invalid_private_reference';
            $privateDatabaseReferences++;
            $missingAssets[] = [
                'asset_ref' => $assetRef,
                'reason' => 'invalid_private_reference',
                'published_program_assignments' => $publishedAssignments,
                'current_program_assignments' => $currentAssignments,
            ];
        } elseif (!isset($privateFiles[$reference])) {
            $record['storage_state'] = 'missing';
            $privateDatabaseReferences++;
            $missingAssets[] = [
                'asset_ref' => $assetRef,
                'reason' => 'missing',
                'published_program_assignments' => $publishedAssignments,
                'current_program_assignments' => $currentAssignments,
            ];
        } elseif ($privateFiles[$reference]['storage_state'] !== 'present') {
            $record['storage_state'] = 'unreadable';
            $privateDatabaseReferences++;
            $referencedPrivateFiles[$reference] = true;
            $missingAssets[] = [
                'asset_ref' => $assetRef,
                'reason' => 'unreadable',
                'published_program_assignments' => $publishedAssignments,
                'current_program_assignments' => $currentAssignments,
            ];
        } else {
            $record['storage_state'] = 'present';
            $record['size_bytes'] = $privateFiles[$reference]['size_bytes'];
            $record['fast_start'] = $privateFiles[$reference]['fast_start'];

            if (isset($privateFiles[$reference]['sha256'])) {
                $record['sha256'] = $privateFiles[$reference]['sha256'];
            }

            $privateDatabaseReferences++;
            $referencedPrivateFiles[$reference] = true;
        }

        if ($lessonRows > 1) {
            $duplicateReferences[] = [
                'asset_ref' => $assetRef,
                'lesson_rows' => $lessonRows,
                'published_program_assignments' => $publishedAssignments,
                'current_program_assignments' => $currentAssignments,
            ];
        }

        $assets[] = $record;
    }

    $orphanFiles = [];
    $fastStart = ['yes' => 0, 'no' => 0, 'invalid' => 0, 'unreadable' => 0];
    $totalPrivateBytes = 0;
    $referencedPrivateBytes = 0;
    $orphanBytes = 0;
    $hashGroups = [];
    $unreadableManagedFiles = 0;

    foreach ($privateFiles as $reference => $metadata) {
        $assetRef = amt_safe_asset_ref(WpPrivateCourseAssetStore::PROVIDER, $reference);
        $sizeBytes = is_int($metadata['size_bytes']) ? $metadata['size_bytes'] : 0;
        $totalPrivateBytes += $sizeBytes;
        $fastStart[$metadata['fast_start']]++;

        if ($metadata['storage_state'] !== 'present') {
            $unreadableManagedFiles++;
        }

        if (isset($metadata['sha256'])) {
            $hashGroups[$metadata['sha256']][] = $assetRef;
        }

        if (isset($referencedPrivateFiles[$reference])) {
            $referencedPrivateBytes += $sizeBytes;
            continue;
        }

        $orphanBytes += $sizeBytes;
        $orphan = [
            'asset_ref' => $assetRef,
            'storage_state' => $metadata['storage_state'],
            'size_bytes' => $metadata['size_bytes'],
            'fast_start' => $metadata['fast_start'],
        ];

        if (isset($metadata['sha256'])) {
            $orphan['sha256'] = $metadata['sha256'];
        }

        $orphanFiles[] = $orphan;
    }

    $duplicateContent = [];

    if ($hashFiles) {
        foreach ($hashGroups as $checksum => $assetRefs) {
            if (count($assetRefs) < 2) {
                continue;
            }

            sort($assetRefs, SORT_STRING);
            $duplicateContent[] = [
                'sha256' => $checksum,
                'asset_refs' => $assetRefs,
            ];
        }
    }

    usort($assets, static fn (array $left, array $right): int => strcmp($left['asset_ref'], $right['asset_ref']));
    usort($missingAssets, static fn (array $left, array $right): int => strcmp($left['asset_ref'], $right['asset_ref']));
    usort($duplicateReferences, static fn (array $left, array $right): int => strcmp($left['asset_ref'], $right['asset_ref']));
    usort($orphanFiles, static fn (array $left, array $right): int => strcmp($left['asset_ref'], $right['asset_ref']));
    usort($duplicateContent, static fn (array $left, array $right): int => strcmp($left['sha256'], $right['sha256']));

    $report = [
        'report_version' => 1,
        'generated_at_utc' => gmdate('c'),
        'mode' => 'read_only_local_inventory',
        'bootstrap_disclosure' => 'WordPress SHORTINIT opens the local database and may load core DB/object-cache drop-ins; this script issues SELECT queries and filesystem reads only.',
        'storage_path_source' => defined('AM_TOOLKIT_PRIVATE_STORAGE_PATH') ? 'constant' : 'default',
        'hashing' => [
            'enabled' => $hashFiles,
            'algorithm' => $hashFiles ? 'sha256_streamed' : null,
            'files_hashed' => $hashFiles ? count(array_filter($privateFiles, static fn (array $file): bool => isset($file['sha256']))) : 0,
            'duplicate_content_groups' => count($duplicateContent),
        ],
        'summary' => [
            'database_video_references' => count($assets),
            'private_database_references' => $privateDatabaseReferences,
            'external_database_references' => $externalDatabaseReferences,
            'incomplete_database_references' => $incompleteDatabaseReferences,
            'references_used_by_published_programs' => $publishedReferenceCount,
            'references_used_by_current_programs' => $currentReferenceCount,
            'private_video_files' => count($privateFiles),
            'referenced_private_files' => count($referencedPrivateFiles),
            'missing_invalid_or_unreadable_private_references' => count($missingAssets),
            'duplicate_reference_groups' => count($duplicateReferences),
            'orphan_private_files' => count($orphanFiles),
            'unreadable_managed_files' => $unreadableManagedFiles,
            'ignored_private_directory_entries' => $ignoredEntries,
            'size_bytes' => [
                'known_private_total' => $totalPrivateBytes,
                'known_referenced_private' => $referencedPrivateBytes,
                'known_orphan_private' => $orphanBytes,
            ],
            'fast_start_private_files' => $fastStart,
        ],
        'assets' => $assets,
        'missing_assets' => $missingAssets,
        'duplicate_references' => $duplicateReferences,
        'orphan_files' => $orphanFiles,
        'duplicate_content' => $duplicateContent,
        'limitations' => [
            'Published usage is inferred by joining program assignments to the current mutable lesson row; program versions do not store historical video references.',
            'Previously replaced or unlinked video references cannot be reconstructed from the current schema.',
            'SHORTINIT does not load plugin hooks, so the storage path uses AM_TOOLKIT_PRIVATE_STORAGE_PATH when defined, otherwise the store default.',
            'Orphan means no current lesson row points to a managed private reference; this report never deletes or quarantines a file.',
        ],
    ];

    echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
} catch (Throwable) {
    fwrite(STDERR, "Local course video audit failed safely; no changes were made.\n");
    exit(1);
}
