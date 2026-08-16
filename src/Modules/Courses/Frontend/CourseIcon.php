<?php

namespace AMToolkit\Modules\Courses\Frontend;

defined('ABSPATH') || exit;

final class CourseIcon
{
    public const ARROW_LEFT = 'arrow-left';
    public const ARROW_RIGHT = 'arrow-right';
    public const DOWNLOAD = 'download';
    public const CALENDAR = 'calendar';
    public const EXTERNAL = 'external';

    public static function render(string $name): string
    {
        return match ($name) {
            self::ARROW_LEFT => self::arrowLeft(),
            self::ARROW_RIGHT => self::arrowRight(),
            self::DOWNLOAD => self::download(),
            self::CALENDAR => self::calendar(),
            self::EXTERNAL => self::external(),
            default => '',
        };
    }

    private static function arrowLeft(): string
    {
        return '<svg class="am-course-icon am-course-icon--arrow-left" aria-hidden="true" focusable="false" viewBox="0 0 24 24"><path d="M12 22C17.5228 22 22 17.5228 22 12C22 6.47715 17.5228 2 12 2C6.47715 2 2 6.47715 2 12C2 17.5228 6.47715 22 12 22Z"/><path d="M13.26 15.53L9.74 12L13.26 8.47"/></svg>';
    }

    private static function arrowRight(): string
    {
        return '<svg class="am-course-icon am-course-icon--arrow-right" aria-hidden="true" focusable="false" viewBox="0 0 24 24"><path d="M12 22C17.5228 22 22 17.5228 22 12C22 6.47715 17.5228 2 12 2C6.47715 2 2 6.47715 2 12C2 17.5228 6.47715 22 12 22Z"/><path d="M10.74 15.53L14.26 12L10.74 8.47"/></svg>';
    }

    private static function download(): string
    {
        return '<svg class="am-course-icon am-course-icon--download" aria-hidden="true" focusable="false" viewBox="0 0 24 24"><path d="M9 11V17L11 15"/><path d="M9 17L7 15"/><path d="M22 10V15C22 20 20 22 15 22H9C4 22 2 20 2 15V9C2 4 4 2 9 2H14"/><path d="M22 10H18C15 10 14 9 14 6V2L22 10Z"/></svg>';
    }

    private static function calendar(): string
    {
        return '<svg class="am-course-icon am-course-icon--calendar" aria-hidden="true" focusable="false" viewBox="0 0 24 24"><path d="M8 2V5M16 2V5M3.5 9H20.5"/><rect x="3.5" y="4" width="17" height="17" rx="3"/><path d="M8 13H8.01M12 13H12.01M16 13H16.01M8 17H8.01M12 17H12.01"/></svg>';
    }

    private static function external(): string
    {
        return '<svg class="am-course-icon am-course-icon--external" aria-hidden="true" focusable="false" viewBox="0 0 24 24"><path d="M14 4H20V10"/><path d="M20 4L11 13"/><path d="M10 6H6C4.9 6 4 6.9 4 8V18C4 19.1 4.9 20 6 20H16C17.1 20 18 19.1 18 18V14"/></svg>';
    }
}
