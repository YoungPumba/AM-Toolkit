<?php

namespace AMToolkit\Modules\WooCommerce;

use AMToolkit\Core\Diagnostics\RequestId;
use AMToolkit\Core\Diagnostics\TechnicalLogger;
use AMToolkit\Core\Diagnostics\WpTechnicalLogger;
use AMToolkit\Modules\Courses\Services\CourseAccessLifecycle;
use AMToolkit\Modules\Courses\Domain\SubscriptionStatusPolicy;

defined('ABSPATH') || exit;

final class WooCommerceSubscriptionsAccessHandler
{
    public function __construct(
        private CourseAccessLifecycle $access,
        private WooCommerceAccessRecordFactory $records,
        private ?SubscriptionStatusPolicy $statuses = null,
        private ?TechnicalLogger $logger = null
    ) {
        $this->statuses ??= new SubscriptionStatusPolicy();
        $this->logger ??= new WpTechnicalLogger();
    }

    public function boot(): void
    {
        if (!class_exists('WC_Subscription')) {
            return;
        }

        add_action(
            'woocommerce_subscription_status_updated',
            [$this, 'statusUpdated'],
            20,
            3
        );
    }

    public function statusUpdated(mixed $subscription, string $newStatus, string $oldStatus): void
    {
        if (!$subscription instanceof \WC_Subscription) {
            return;
        }

        $subscriptionId = $subscription->get_id();
        $requestId = RequestId::generate();
        $action = $this->statuses?->actionFor($newStatus);

        if ($action === SubscriptionStatusPolicy::GRANT) {
            $result = $this->access->activateSubscription(
                $subscription->get_user_id(),
                $subscriptionId,
                $this->records->productIds($subscription),
                [
                    'provider' => 'woocommerce-subscriptions',
                    'old_status' => $oldStatus,
                    'new_status' => $newStatus,
                ],
                $requestId
            );
        } elseif ($action === SubscriptionStatusPolicy::RETAIN) {
            return;
        } elseif ($action === SubscriptionStatusPolicy::REVOKE) {
            $result = $this->access->endSubscription($subscriptionId, $requestId);
        } else {
            return;
        }

        if (is_wp_error($result)) {
            $this->logger?->error(
                'WooCommerce subscription course access event failed.',
                [
                    'error_code' => $result->get_error_code(),
                    'object_type' => 'subscription',
                    'object_id' => $subscriptionId,
                ]
            );
        }
    }
}
