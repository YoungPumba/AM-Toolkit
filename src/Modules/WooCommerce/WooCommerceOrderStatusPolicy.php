<?php

namespace AMToolkit\Modules\WooCommerce;

defined('ABSPATH') || exit;

final class WooCommerceOrderStatusPolicy
{
    public const GRANT = 'grant';
    public const REVOKE = 'revoke';
    public const RETAIN = 'retain';

    /**
     * @param list<string> $paidStatuses
     */
    public function actionFor(string $status, array $paidStatuses): string
    {
        $status = sanitize_key($status);
        $paidStatuses = array_map('sanitize_key', $paidStatuses);

        if (in_array($status, $paidStatuses, true)) {
            return self::GRANT;
        }

        if (in_array($status, ['cancelled', 'failed', 'refunded'], true)) {
            return self::REVOKE;
        }

        return self::RETAIN;
    }
}
