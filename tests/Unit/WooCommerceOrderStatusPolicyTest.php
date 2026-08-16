<?php

declare(strict_types=1);

namespace AMToolkit\Tests\Unit;

use AMToolkit\Modules\WooCommerce\WooCommerceOrderStatusPolicy;
use AMToolkit\Modules\WooCommerce\WooCommerceOrderAccessHandler;
use AMToolkit\Modules\WooCommerce\WooCommerceAccessRecordFactory;
use AMToolkit\Modules\Courses\Services\CourseAccessLifecycle;
use AMToolkit\Modules\Courses\Domain\CourseAccessSource;
use PHPUnit\Framework\TestCase;

final class WooCommerceOrderStatusPolicyTest extends TestCase
{
    private WooCommerceOrderStatusPolicy $policy;

    protected function setUp(): void
    {
        $this->policy = new WooCommerceOrderStatusPolicy();
    }

    public function testPaidStatusesGrantAccess(): void
    {
        self::assertSame(
            WooCommerceOrderStatusPolicy::GRANT,
            $this->policy->actionFor('processing', ['processing', 'completed'])
        );
        self::assertSame(
            WooCommerceOrderStatusPolicy::GRANT,
            $this->policy->actionFor('completed', ['processing', 'completed'])
        );
    }

    public function testTerminalUnpaidStatusesRevokeAccess(): void
    {
        foreach (['cancelled', 'failed', 'refunded'] as $status) {
            self::assertSame(
                WooCommerceOrderStatusPolicy::REVOKE,
                $this->policy->actionFor($status, ['processing', 'completed'])
            );
        }
    }

    public function testPendingAndOnHoldRetainTheCurrentState(): void
    {
        foreach (['pending', 'on-hold', 'custom-review'] as $status) {
            self::assertSame(
                WooCommerceOrderStatusPolicy::RETAIN,
                $this->policy->actionFor($status, ['processing', 'completed'])
            );
        }
    }

    public function testOrderHandlerRevokesGrantsAfterAFullRefund(): void
    {
        $mappings = new MemoryCourseMappingStore([101 => [7, 8]]);
        $entitlements = new MemoryCourseEntitlementGateway();
        $lifecycle = new CourseAccessLifecycle($mappings, $entitlements);
        $lifecycle->grantPurchase(5, 900, [101]);

        $handler = new WooCommerceOrderAccessHandler(
            $lifecycle,
            new WooCommerceAccessRecordFactory()
        );
        $handler->statusChanged(900, 'completed', 'refunded');

        self::assertCount(0, $entitlements->activeBySource(CourseAccessSource::PURCHASE, 900));
        self::assertFalse($entitlements->hasActive(5, 7));
        self::assertFalse($entitlements->hasActive(5, 8));
    }
}
