<?php

namespace AMToolkit\Modules\Courses;

use AMToolkit\Modules\Courses\Services\CourseProductMappingManager;

defined('ABSPATH') || exit;

final class CourseMappings
{
    private static ?CourseProductMappingManager $manager = null;

    public static function mapProduct(int $productId, int $courseId): bool|\WP_Error
    {
        return self::manager()->map($productId, $courseId);
    }

    public static function unmapProduct(int $productId, int $courseId): bool|\WP_Error
    {
        return self::manager()->unmap($productId, $courseId);
    }

    public static function replaceManager(CourseProductMappingManager $manager): void
    {
        self::$manager = $manager;
    }

    private static function manager(): CourseProductMappingManager
    {
        if (self::$manager === null) {
            self::$manager = new CourseProductMappingManager(
                new WpdbProductCourseMappingStore()
            );
        }

        return self::$manager;
    }
}
