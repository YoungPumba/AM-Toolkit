<?php

namespace AMToolkit\Modules\Access;

defined('ABSPATH') || exit;

interface EntitlementStore
{
    /**
     * @return array{id: int, created: bool}|\WP_Error
     */
    public function create(array $grant): array|\WP_Error;

    public function hasActiveGrant(
        int $userId,
        string $resourceType,
        int $resourceId,
        string $at
    ): bool;

    public function findByGrantKey(string $grantKey): ?array;

    public function revoke(string $grantKey, string $revokedAt): bool|\WP_Error;

    public function restore(array $grant): bool|\WP_Error;
}
