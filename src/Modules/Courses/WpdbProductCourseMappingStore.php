<?php

namespace AMToolkit\Modules\Courses;

use AMToolkit\Modules\Courses\Contracts\ProductCourseMappingStore;

defined('ABSPATH') || exit;

final class WpdbProductCourseMappingStore implements ProductCourseMappingStore
{
    private \wpdb $database;

    private string $table;

    public function __construct(?\wpdb $database = null, ?string $table = null)
    {
        global $wpdb;

        $this->database = $database ?? $wpdb;
        $this->table = $table ?? CoursesSchema::productMappingsTable();
    }

    public function map(int $productId, int $courseId): bool|\WP_Error
    {
        $now = current_time('mysql', true);
        $sql = $this->database->prepare(
            "INSERT INTO {$this->table} (
                product_id, course_id, status, created_at, updated_at
            ) VALUES (%d, %d, 'active', %s, %s)
            ON DUPLICATE KEY UPDATE status = 'active', updated_at = VALUES(updated_at)",
            $productId,
            $courseId,
            $now,
            $now
        );
        $result = $this->database->query($sql);

        if ($result === false) {
            return new \WP_Error(
                'am_toolkit_course_mapping_write_failed',
                __('Nie udało się zapisać mapowania produktu na kurs.', 'am-toolkit'),
                ['database_error' => $this->database->last_error]
            );
        }

        return true;
    }

    public function unmap(int $productId, int $courseId): bool|\WP_Error
    {
        $result = $this->database->query(
            $this->database->prepare(
                "UPDATE {$this->table}
                SET status = 'inactive', updated_at = %s
                WHERE product_id = %d AND course_id = %d AND status = 'active'",
                current_time('mysql', true),
                $productId,
                $courseId
            )
        );

        if ($result === false) {
            return new \WP_Error(
                'am_toolkit_course_mapping_write_failed',
                __('Nie udało się wyłączyć mapowania produktu na kurs.', 'am-toolkit'),
                ['database_error' => $this->database->last_error]
            );
        }

        return true;
    }

    public function courseIdsForProducts(array $productIds): array|\WP_Error
    {
        $productIds = array_values(array_unique(array_filter(
            array_map('absint', $productIds),
            static fn (int $id): bool => $id > 0
        )));

        if ($productIds === []) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, count($productIds), '%d'));
        $sql = $this->database->prepare(
            "SELECT DISTINCT course_id FROM {$this->table}
            WHERE product_id IN ({$placeholders}) AND status = 'active'
            ORDER BY course_id ASC",
            ...$productIds
        );
        $courseIds = $this->database->get_col($sql);

        if ($this->database->last_error !== '') {
            return new \WP_Error(
                'am_toolkit_course_mapping_read_failed',
                __('Nie udało się odczytać mapowań produktów na kursy.', 'am-toolkit'),
                ['database_error' => $this->database->last_error]
            );
        }

        return array_values(array_map('intval', $courseIds));
    }
}
