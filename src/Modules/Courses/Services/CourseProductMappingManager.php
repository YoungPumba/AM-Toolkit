<?php

namespace AMToolkit\Modules\Courses\Services;

use AMToolkit\Modules\Courses\Contracts\ProductCourseMappingStore;

defined('ABSPATH') || exit;

final class CourseProductMappingManager
{
    public function __construct(private ProductCourseMappingStore $mappings)
    {
    }

    public function map(int $productId, int $courseId): bool|\WP_Error
    {
        if ($productId <= 0 || $courseId <= 0) {
            return new \WP_Error(
                'am_toolkit_invalid_course_mapping',
                __('Produkt i kurs muszą mieć prawidłowe identyfikatory.', 'am-toolkit')
            );
        }

        return $this->mappings->map($productId, $courseId);
    }

    public function unmap(int $productId, int $courseId): bool|\WP_Error
    {
        if ($productId <= 0 || $courseId <= 0) {
            return new \WP_Error(
                'am_toolkit_invalid_course_mapping',
                __('Produkt i kurs muszą mieć prawidłowe identyfikatory.', 'am-toolkit')
            );
        }

        return $this->mappings->unmap($productId, $courseId);
    }
}
