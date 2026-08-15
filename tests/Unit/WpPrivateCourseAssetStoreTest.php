<?php

declare(strict_types=1);

namespace AMToolkit\Tests\Unit;

use AMToolkit\Modules\Courses\Domain\ProtectedAsset;
use AMToolkit\Modules\Courses\WpPrivateCourseAssetStore;
use PHPUnit\Framework\TestCase;

final class WpPrivateCourseAssetStoreTest extends TestCase
{
    private string $directory;
    private WpPrivateCourseAssetStore $store;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'amt-course-assets-' . bin2hex(random_bytes(6));
        mkdir($this->directory . DIRECTORY_SEPARATOR . 'videos', 0777, true);
        $this->store = new WpPrivateCourseAssetStore($this->directory);
    }

    protected function tearDown(): void
    {
        $videoDirectory = $this->directory . DIRECTORY_SEPARATOR . 'videos';

        foreach (glob($videoDirectory . DIRECTORY_SEPARATOR . '*') ?: [] as $file) {
            unlink($file);
        }

        rmdir($videoDirectory);
        rmdir($this->directory);
    }

    public function testValidOpaqueReferenceResolvesInsideConfiguredDirectory(): void
    {
        $reference = 'videos/12345678-1234-4123-8123-123456789012.mp4';
        $path = $this->directory . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $reference);
        file_put_contents($path, 'video-bytes');

        $asset = $this->store->locate($reference, 'Słowa kluczowe i hashtagi');

        self::assertInstanceOf(ProtectedAsset::class, $asset);
        self::assertSame(realpath($path), $asset->path());
        self::assertSame('video/mp4', $asset->mimeType());
        self::assertSame(11, $asset->size());
        self::assertStringEndsWith('.mp4', $asset->downloadName());
    }

    /** @dataProvider unsafeReferences */
    public function testUnsafeOrPublicLookingReferencesAreRejected(string $reference): void
    {
        $asset = $this->store->locate($reference, 'plik');

        self::assertInstanceOf(\WP_Error::class, $asset);
        self::assertSame('am_toolkit_course_asset_not_found', $asset->get_error_code());
    }

    /** @return list<array{string}> */
    public function unsafeReferences(): array
    {
        return [
            ['../wp-config.php'],
            ['videos/not-a-uuid.mp4'],
            ['https://example.com/video.mp4'],
            ['videos/12345678-1234-4123-8123-123456789012.php'],
        ];
    }
}
