<?php

namespace AMToolkit\Modules\Courses\Domain;

defined('ABSPATH') || exit;

final class SubscriptionStatusPolicy
{
    public const GRANT = 'grant';
    public const RETAIN = 'retain';
    public const REVOKE = 'revoke';
    public const IGNORE = 'ignore';

    public function actionFor(string $status): string
    {
        $status = sanitize_key($status);

        if ($status === 'active') {
            return self::GRANT;
        }

        if ($status === 'pending-cancel') {
            return self::RETAIN;
        }

        if (in_array($status, ['pending', 'on-hold', 'cancelled', 'expired'], true)) {
            return self::REVOKE;
        }

        return self::IGNORE;
    }
}
