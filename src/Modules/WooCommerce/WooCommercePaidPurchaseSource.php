<?php

namespace AMToolkit\Modules\WooCommerce;

use AMToolkit\Modules\Courses\Contracts\HistoricalPurchaseSource;
use AMToolkit\Modules\Courses\Domain\PurchaseBatch;

defined('ABSPATH') || exit;

final class WooCommercePaidPurchaseSource implements HistoricalPurchaseSource
{
    public function __construct(private WooCommerceAccessRecordFactory $records)
    {
    }

    public function fetch(int $page, int $limit): PurchaseBatch|\WP_Error
    {
        $result = wc_get_orders([
            'status' => wc_get_is_paid_statuses(),
            'limit' => $limit,
            'page' => $page,
            'paginate' => true,
            'orderby' => 'ID',
            'order' => 'ASC',
            'return' => 'objects',
        ]);

        if (
            !is_object($result)
            || !property_exists($result, 'orders')
            || !is_array($result->orders)
            || !property_exists($result, 'max_num_pages')
            || !is_numeric($result->max_num_pages)
        ) {
            return new \WP_Error(
                'am_toolkit_course_backfill_query_failed',
                __('WooCommerce nie zwrócił stronicowanego wyniku zamówień.', 'am-toolkit')
            );
        }

        $records = [];

        foreach ($result->orders as $order) {
            if (!$order instanceof \WC_Order) {
                continue;
            }

            if ($order->get_user_id() <= 0 || $this->records->isSubscriptionOrder($order)) {
                continue;
            }

            $record = $this->records->purchase($order);

            if (!is_wp_error($record)) {
                $records[] = $record;
            }
        }

        return new PurchaseBatch($records, $page < (int) $result->max_num_pages);
    }
}
