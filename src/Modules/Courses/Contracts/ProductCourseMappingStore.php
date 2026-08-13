<?php

namespace AMToolkit\Modules\Courses\Contracts;

defined('ABSPATH') || exit;

interface ProductCourseMappingStore
{
    public function map(int $productId, int $courseId): bool|\WP_Error;

    public function unmap(int $productId, int $courseId): bool|\WP_Error;

    /** @param list<int> $productIds @return list<int>|\WP_Error */
    public function courseIdsForProducts(array $productIds): array|\WP_Error;
}
