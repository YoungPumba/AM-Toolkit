<?php

namespace AMToolkit\Modules\WooCommerce;

use AMToolkit\Core\Diagnostics\RequestId;
use AMToolkit\Core\Diagnostics\TechnicalLogger;
use AMToolkit\Core\Diagnostics\WpTechnicalLogger;
use AMToolkit\Modules\Courses\Services\CourseAccessLifecycle;

defined('ABSPATH') || exit;

final class WooCommerceOrderAccessHandler
{
    public function __construct(
        private CourseAccessLifecycle $access,
        private WooCommerceAccessRecordFactory $records,
        private ?TechnicalLogger $logger = null,
        private ?WooCommerceOrderStatusPolicy $statusPolicy = null
    ) {
        $this->logger ??= new WpTechnicalLogger();
        $this->statusPolicy ??= new WooCommerceOrderStatusPolicy();
    }

    public function boot(): void
    {
        add_action('woocommerce_payment_complete', [$this, 'paymentCompleted'], 20, 1);
        add_action('woocommerce_order_status_changed', [$this, 'statusChanged'], 20, 4);
    }

    public function paymentCompleted(int $orderId): void
    {
        $this->processOrderId($orderId);
    }

    public function statusChanged(
        int $orderId,
        string $oldStatus,
        string $newStatus,
        mixed $order = null
    ): void {
        $action = $this->statusPolicy?->actionFor($newStatus, \wc_get_is_paid_statuses());

        if ($action === WooCommerceOrderStatusPolicy::REVOKE) {
            $result = $this->access->revokePurchase($orderId, RequestId::generate());

            if (is_wp_error($result)) {
                $this->logError($result, $orderId);
            }

            return;
        }

        if ($action !== WooCommerceOrderStatusPolicy::GRANT) {
            return;
        }

        $this->processOrderId($orderId, $order);
    }

    public function processOrderId(int $orderId, mixed $candidate = null): bool|\WP_Error
    {
        $order = $candidate instanceof \WC_Order ? $candidate : wc_get_order($orderId);

        if (!$order instanceof \WC_Order || !$order->is_paid()) {
            return false;
        }

        if ($this->records->isSubscriptionOrder($order)) {
            return false;
        }

        $purchase = $this->records->purchase($order);

        if (is_wp_error($purchase)) {
            $this->logError($purchase, $orderId);
            return $purchase;
        }

        $result = $this->access->grantPurchaseRecord($purchase, RequestId::generate());

        if (is_wp_error($result)) {
            $this->logError($result, $orderId);
            return $result;
        }

        return true;
    }

    private function logError(\WP_Error $error, int $orderId): void
    {
        $this->logger?->error(
            'WooCommerce course access event failed.',
            [
                'error_code' => $error->get_error_code(),
                'object_type' => 'order',
                'object_id' => $orderId,
            ]
        );
    }
}
