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

    /** @param array{request_id?: string} $context */
    public static function revoke(string $grantKey, array $context = []): bool|\WP_Error
    {
        return self::manager()->revoke($grantKey, $context);
    }

    public static function revokeSource(
        int $userId,
        string $resourceType,
        int $resourceId,
        string $sourceType,
        int $sourceId,
        array $context = []
    ): bool|\WP_Error {
        return self::manager()->revokeSource(
            $userId,
            $resourceType,
            $resourceId,
            $sourceType,
            $sourceId,
            $context
        );
    }

    /** @param array{request_id?: string} $context */
    public static function revokeAllSource(
        string $sourceType,
        int $sourceId,
        array $context = []
    ): int|\WP_Error {
        return self::manager()->revokeAllSource($sourceType, $sourceId, $context);
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
