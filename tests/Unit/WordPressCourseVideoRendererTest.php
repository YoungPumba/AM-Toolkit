<?php

declare(strict_types=1);

use AMToolkit\Modules\Courses\Frontend\WordPressCourseVideoRenderer;
use PHPUnit\Framework\TestCase;

final class WordPressCourseVideoRendererTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['amt_test_enqueued_styles'] = [];
        $GLOBALS['amt_test_enqueued_scripts'] = [];
    }

    public function testItRendersProtectedQueryStringUrlAsMp4Source(): void
    {
        $renderer = new WordPressCourseVideoRenderer();
        $url = 'https://example.test/admin-post.php?action=asset&_wpnonce=test';

        $html = $renderer->render($url);

        self::assertIsString($html);
        self::assertStringContainsString('<video class="wp-video-shortcode"', $html);
        self::assertStringContainsString('preload="metadata"', $html);
        self::assertStringContainsString('playsinline="playsinline"', $html);
        self::assertStringContainsString('type="video/mp4"', $html);
        self::assertStringContainsString('action=asset&amp;_wpnonce=test', $html);
        self::assertSame(['wp-mediaelement'], $GLOBALS['amt_test_enqueued_styles']);
        self::assertSame(['wp-mediaelement'], $GLOBALS['amt_test_enqueued_scripts']);
    }

    public function testItRejectsEmptySource(): void
    {
        $result = (new WordPressCourseVideoRenderer())->render('');

        self::assertInstanceOf(WP_Error::class, $result);
        self::assertSame('am_toolkit_course_video_unavailable', $result->get_error_code());
    }

    public function testNativeVariantKeepsProtectedSourceAndDoesNotInitializeMediaElement(): void
    {
        $url = 'https://example.test/admin-post.php?action=asset&_wpnonce=test';
        $html = (new WordPressCourseVideoRenderer())->render($url, [
            'player_mode' => 'native',
            'poster' => 'https://example.test/poster.jpg?x=1&y=2',
        ]);

        self::assertIsString($html);
        self::assertStringContainsString('<video class="am-course-native-video"', $html);
        self::assertStringNotContainsString('wp-video-shortcode', $html);
        self::assertStringNotContainsString('mejs', $html);
        self::assertStringContainsString('controls="controls"', $html);
        self::assertStringContainsString('preload="metadata"', $html);
        self::assertStringContainsString('playsinline="playsinline"', $html);
        self::assertStringContainsString('type="video/mp4"', $html);
        self::assertStringContainsString('action=asset&amp;_wpnonce=test', $html);
        self::assertStringContainsString('poster="https://example.test/poster.jpg?x=1&amp;y=2"', $html);
        self::assertSame([], $GLOBALS['amt_test_enqueued_styles']);
        self::assertSame([], $GLOBALS['amt_test_enqueued_scripts']);
    }

    public function testUnknownModeFallsBackToStandardPlayer(): void
    {
        $html = (new WordPressCourseVideoRenderer())->render('https://example.test/video', ['player_mode' => ['native']]);
        self::assertStringContainsString('wp-video-shortcode', $html);
        self::assertSame(['wp-mediaelement'], $GLOBALS['amt_test_enqueued_scripts']);
    }
}
