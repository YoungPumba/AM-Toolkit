<?php

declare(strict_types=1);

namespace AMToolkit\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class CourseMeetingResponsiveCssTest extends TestCase
{
    private string $stylesheet;

    protected function setUp(): void
    {
        $stylesheet = file_get_contents(__DIR__ . '/../../assets/css/courses.css');

        self::assertIsString($stylesheet);
        $this->stylesheet = $stylesheet;
    }

    public function testMeetingCopyCanShrinkAndWrapLongZoomContent(): void
    {
        $rule = $this->rule('.am-course-meeting__copy');

        self::assertStringContainsString('min-width: 0', $rule);
        self::assertStringContainsString('max-width: 100%', $rule);
        self::assertStringContainsString('overflow-wrap: anywhere', $rule);
        self::assertStringContainsString('word-break: break-word', $rule);
    }

    public function testMeetingSectionAndTelegramCopyStayInsideTheirContainer(): void
    {
        $section = $this->rule('.am-course-meetings');
        $telegramCopy = $this->rule('.am-course-meetings__telegram > span');

        self::assertStringContainsString('width: 100%', $section);
        self::assertStringContainsString('min-width: 0', $section);
        self::assertStringContainsString('max-width: 100%', $section);
        self::assertStringContainsString('overflow-wrap: anywhere', $telegramCopy);
        self::assertStringContainsString('word-break: break-word', $telegramCopy);
        self::assertStringNotContainsString('overflow-x: hidden', $this->stylesheet);
    }

    private function rule(string $selector): string
    {
        $matched = preg_match(
            '/' . preg_quote($selector, '/') . '\\s*\\{(?<declarations>[^}]*)\\}/s',
            $this->stylesheet,
            $matches
        );

        self::assertSame(1, $matched, sprintf('Nie znaleziono reguły CSS dla selektora %s.', $selector));

        return (string) ($matches['declarations'] ?? '');
    }
}
