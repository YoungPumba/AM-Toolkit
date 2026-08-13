<?php

declare(strict_types=1);

namespace AMToolkit\Modules\WooCommerce {
    function wc_get_orders(array $args = []): mixed
    {
        return $GLOBALS['amt_test_wc_orders_result'] ?? null;
    }

    /** @return list<string> */
    function wc_get_is_paid_statuses(): array
    {
        return ['processing', 'completed'];
    }
}

namespace AMToolkit\Tests\Unit {
    use AMToolkit\Modules\WooCommerce\WooCommerceAccessRecordFactory;
    use AMToolkit\Modules\WooCommerce\WooCommercePaidPurchaseSource;
    use PHPUnit\Framework\TestCase;

    final class WooCommercePaidPurchaseSourceTest extends TestCase
    {
        protected function tearDown(): void
        {
            unset($GLOBALS['amt_test_wc_orders_result']);
        }

        public function testAcceptsPaginatedStdClassReturnedByWooCommerce(): void
        {
            $GLOBALS['amt_test_wc_orders_result'] = (object) [
                'orders' => [],
                'total' => 0,
                'max_num_pages' => 1,
            ];

            $batch = (new WooCommercePaidPurchaseSource(new WooCommerceAccessRecordFactory()))
                ->fetch(1, 50);

            self::assertNotInstanceOf(\WP_Error::class, $batch);
            self::assertSame([], $batch->records());
            self::assertFalse($batch->hasMore());
        }

        public function testRejectsMalformedPaginatedResult(): void
        {
            $GLOBALS['amt_test_wc_orders_result'] = (object) [
                'orders' => 'not-an-array',
                'max_num_pages' => 1,
            ];

            $result = (new WooCommercePaidPurchaseSource(new WooCommerceAccessRecordFactory()))
                ->fetch(1, 50);

            self::assertInstanceOf(\WP_Error::class, $result);
            self::assertSame('am_toolkit_course_backfill_query_failed', $result->get_error_code());
        }
    }
}
