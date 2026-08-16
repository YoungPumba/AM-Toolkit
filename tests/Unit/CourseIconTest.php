<?php

declare(strict_types=1);

use AMToolkit\Modules\Courses\Frontend\CourseIcon;
use PHPUnit\Framework\TestCase;

final class CourseIconTest extends TestCase
{
    public function testItRendersDecorativeCurrentColorIcons(): void
    {
        foreach ([CourseIcon::ARROW_LEFT, CourseIcon::ARROW_RIGHT, CourseIcon::DOWNLOAD] as $name) {
            $icon = CourseIcon::render($name);

            self::assertStringContainsString('<svg', $icon);
            self::assertStringContainsString('aria-hidden="true"', $icon);
            self::assertStringContainsString('focusable="false"', $icon);
            self::assertStringNotContainsString('<defs', $icon);
            self::assertStringNotContainsString('style=', $icon);
        }
    }

    public function testItRejectsUnknownIconNames(): void
    {
        self::assertSame('', CourseIcon::render('unknown'));
    }
}
