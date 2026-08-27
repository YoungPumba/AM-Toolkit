<?php

declare(strict_types=1);

use AMToolkit\Modules\Courses\Domain\Mp4StreamabilityInspector;
use PHPUnit\Framework\TestCase;

final class Mp4StreamabilityInspectorTest extends TestCase
{
    private string $path;

    protected function setUp(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'amt-mp4-');
        self::assertIsString($path);
        $this->path = $path;
    }

    protected function tearDown(): void
    {
        if (is_file($this->path)) {
            unlink($this->path);
        }
    }

    public function testMoovBeforeMediaDataSupportsProgressiveDownload(): void
    {
        file_put_contents(
            $this->path,
            $this->atom('ftyp') . $this->atom('moov', 'index') . $this->atom('mdat', 'video')
        );

        self::assertTrue((new Mp4StreamabilityInspector())->inspect($this->path));
    }

    public function testMoovAfterMediaDataIsNotReadyForProgressiveDownload(): void
    {
        file_put_contents(
            $this->path,
            $this->atom('ftyp') . $this->atom('mdat', 'video') . $this->atom('moov', 'index')
        );

        self::assertFalse((new Mp4StreamabilityInspector())->inspect($this->path));
    }

    public function testMalformedAtomIsRejected(): void
    {
        file_put_contents($this->path, pack('N', 1024) . 'ftyp');

        $result = (new Mp4StreamabilityInspector())->inspect($this->path);

        self::assertInstanceOf(WP_Error::class, $result);
        self::assertSame('am_toolkit_course_video_invalid_mp4', $result->get_error_code());
    }

    private function atom(string $type, string $payload = ''): string
    {
        return pack('N', 8 + strlen($payload)) . $type . $payload;
    }
}
