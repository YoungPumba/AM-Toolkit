<?php

namespace AMToolkit\Modules\Access;

use AMToolkit\Core\Installer;

defined('ABSPATH') || exit;

final class WpdbEntitlementStore implements EntitlementStore
{
    private \wpdb $database;

    private string $table;

    public function __construct(?\wpdb $database = null, ?string $table = null)
    {
        global $wpdb;

        $this->database = $database ?? $wpdb;
        $this->table = $table ?? Installer::accessGrantsTable();
    }

    public function create(array $grant): array|\WP_Error
    {
        $sql = $this->database->prepare(
            "INSERT INTO {$this->table} (
                user_id, resource_type, resource_id, grant_key,
                source_type, source_id, status, starts_at, expires_at,
                granted_at, revoked_at, metadata, created_at, updated_at
            ) VALUES (
                %d, %s, %d, %s,
                %s, %d, %s, NULLIF(%s, ''), NULLIF(%s, ''),
                %s, NULL, NULLIF(%s, ''), %s, %s
            ) ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)",
            $grant['user_id'],
            $grant['resource_type'],
            $grant['resource_id'],
            $grant['grant_key'],
            $grant['source_type'],
            $grant['source_id'],
            $grant['status'],
            $grant['starts_at'] ?? '',
            $grant['expires_at'] ?? '',
            $grant['granted_at'],
            $grant['metadata'] ?? '',
            $grant['created_at'],
            $grant['updated_at']
        );

        $result = $this->database->query($sql);

        if ($result === false) {
            return new \WP_Error(
                'am_toolkit_access_write_failed',
                __('Nie udało się zapisać uprawnienia dostępu.', 'am-toolkit'),
                ['database_error' => $this->database->last_error]
            );
        }

        return [
            'id' => (int) $this->database->insert_id,
            'created' => $result === 1,
        ];
    }

    public function hasActiveGrant(
        int $userId,
        string $resourceType,
        int $resourceId,
        string $at
    ): bool {
        $sql = $this->database->prepare(
            "SELECT id FROM {$this->table}
            WHERE user_id = %d
                AND resource_type = %s
                AND resource_id = %d
                AND status = 'active'
                AND (starts_at IS NULL OR starts_at <= %s)
                AND (expires_at IS NULL OR expires_at > %s)
            LIMIT 1",
            $userId,
            $resourceType,
            $resourceId,
            $at,
            $at
        );

        return (bool) $this->database->get_var($sql);
    }

    public function findByGrantKey(string $grantKey): ?array
    {
        $sql = $this->database->prepare(
            "SELECT * FROM {$this->table} WHERE grant_key = %s LIMIT 1",
            $grantKey
        );

        $grant = $this->database->get_row($sql, ARRAY_A);

        return is_array($grant) ? $grant : null;
    }

    public function revoke(string $grantKey, string $revokedAt): bool|\WP_Error
    {
        $result = $this->database->query(
            $this->database->prepare(
                "UPDATE {$this->table}
                SET status = 'revoked', revoked_at = %s, updated_at = %s
                WHERE grant_key = %s AND status = 'active'",
                $revokedAt,
                $revokedAt,
                $grantKey
            )
        );

        if ($result === false) {
            return new \WP_Error(
                'am_toolkit_access_revoke_failed',
                __('Nie udało się odebrać uprawnienia dostępu.', 'am-toolkit'),
                ['database_error' => $this->database->last_error]
            );
        }

        return $result > 0;
    }

    public function restore(array $grant): bool|\WP_Error
    {
        $result = $this->database->query(
            $this->database->prepare(
                "UPDATE {$this->table}
                SET status = 'active',
                    starts_at = NULLIF(%s, ''),
                    expires_at = NULLIF(%s, ''),
                    granted_at = %s,
                    revoked_at = NULL,
                    metadata = NULLIF(%s, ''),
                    updated_at = %s
                WHERE grant_key = %s AND status = 'revoked'",
                $grant['starts_at'] ?? '',
                $grant['expires_at'] ?? '',
                $grant['granted_at'],
                $grant['metadata'] ?? '',
                $grant['updated_at'],
                $grant['grant_key']
            )
        );

        if ($result === false) {
            return new \WP_Error(
                'am_toolkit_access_restore_failed',
                __('Nie udało się ponownie nadać uprawnienia dostępu.', 'am-toolkit'),
                ['database_error' => $this->database->last_error]
            );
        }

        return $result > 0;
    }
}
