<?php

namespace AMToolkit\Modules\Access;

defined('ABSPATH') || exit;

final class Access
{
    private static ?AccessManager $manager = null;

    public static function userHas(
        int $userId,
        string $resourceType,
        int $resourceId
    ): bool {
        return self::manager()->userHasAccess(
            $userId,
            $resourceType,
            $resourceId
        );
    }

    public static function grant(
        int $userId,
        string $resourceType,
        int $resourceId,
        array $context = []
    ): int|\WP_Error {
        return self::manager()->grant(
            $userId,
            $resourceType,
            $resourceId,
            $context
        );
    }

    public static function revoke(string $grantKey): bool|\WP_Error
    {
        return self::manager()->revoke($grantKey);
    }

    public static function revokeSource(
        int $userId,
        string $resourceType,
        int $resourceId,
        string $sourceType,
        int $sourceId
    ): bool|\WP_Error {
        return self::manager()->revokeSource(
            $userId,
            $resourceType,
            $resourceId,
            $sourceType,
            $sourceId
        );
    }

    public static function replaceManager(AccessManager $manager): void
    {
        self::$manager = $manager;
    }

    private static function manager(): AccessManager
    {
        if (self::$manager === null) {
            self::$manager = AccessManager::createDefault();
        }

        return self::$manager;
    }
}
