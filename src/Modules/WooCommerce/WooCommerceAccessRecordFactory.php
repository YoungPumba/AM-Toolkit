<?php

namespace AMToolkit\Modules\WooCommerce;

use AMToolkit\Modules\Courses\Domain\PurchaseAccessRecord;

defined('ABSPATH') || exit;

final class WooCommerceAccessRecordFactory
{
    public function purchase(\WC_Order $order): PurchaseAccessRecord|\WP_Error
    {
        $userId = $order->get_user_id();

        if ($userId <= 0) {
            return new \WP_Error(
                'am_toolkit_course_access_guest_order',
                __('Dostęp do kursu wymaga zamówienia przypisanego do konta.', 'am-toolkit')
            );
        }

        return new PurchaseAccessRecord(
            $userId,
            $order->get_id(),
            $this->productIds($order),
            [
                'provider' => 'woocommerce',
                'order_status' => $order->get_status(),
            ]
        );
    }

    /** @return list<int> */
    public function productIds(\WC_Order $order): array
    {
        $productIds = [];

        foreach ($order->get_items('line_item') as $item) {
            $variationId = $item->get_variation_id();
            $productId = $item->get_product_id();

            if ($variationId > 0) {
                $productIds[] = $variationId;
            }

            if ($productId > 0) {
                $productIds[] = $productId;
            }
        }

        return array_values(array_unique($productIds));
    }

    public function isSubscriptionOrder(\WC_Order $order): bool
    {
        if (!function_exists('wcs_order_contains_subscription')) {
            return false;
        }

        return (bool) wcs_order_contains_subscription(
            $order,
            ['parent', 'renewal', 'resubscribe', 'switch']
        );
    }
}
