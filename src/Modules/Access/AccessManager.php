<?php

namespace AMToolkit\Modules\Access;

use AMToolkit\Core\Diagnostics\DomainEvent;
use AMToolkit\Core\Diagnostics\RequestId;
use AMToolkit\Core\Diagnostics\TechnicalLogger;
use AMToolkit\Core\Diagnostics\WpTechnicalLogger;

defined('ABSPATH') || exit;

final class AccessManager
{
    public function __construct(
        private EntitlementStore $entitlements,
        private ActivityEventStore $events,
        private ?TechnicalLogger $logger = null
    ) {
        $this->logger ??= new WpTechnicalLogger();
    }

    public static function createDefault(): self
    {
        return new self(
            new WpdbEntitlementStore(),
            new WpdbActivityEventStore()
        );
    }

    public function userHasAccess(
        int $userId,
        string $resourceType,
        int $resourceId
    ): bool {
        $resourceType = sanitize_key($resourceType);

        if ($userId <= 0 || $resourceId <= 0 || $resourceType === '') {
            return false;
        }

        $allowed = $this->entitlements->hasActiveGrant(
            $userId,
            $resourceType,
            $resourceId,
            current_time('mysql', true)
        );

        return (bool) apply_filters(
            'am_toolkit_user_has_access',
            $allowed,
            $userId,
            $resourceType,
            $resourceId
        );
    }

    /**
     * @param array{
     *     grant_key?: string,
     *     source_type?: string,
     *     source_id?: int,
     *     starts_at?: mixed,
     *     expires_at?: mixed,
     *     metadata?: array<string, mixed>,
     *     request_id?: string
     * } $context
     */
    public function grant(
        int $userId,
        string $resourceType,
        int $resourceId,
        array $context = []
    ): int|\WP_Error {
        $resourceType = sanitize_key($resourceType);
        $sourceType = sanitize_key((string) ($context['source_type'] ?? 'manual'));
        $sourceId = absint($context['source_id'] ?? 0);
        $requestId = RequestId::normalize(
            isset($context['request_id']) ? (string) $context['request_id'] : null
        );

        if ($userId <= 0 || $resourceId <= 0 || $resourceType === '') {
            return new \WP_Error(
                'am_toolkit_invalid_access_target',
                __('Nieprawidłowy użytkownik lub zasób dostępu.', 'am-toolkit')
            );
        }

        if ($sourceType === '') {
            return new \WP_Error(
                'am_toolkit_invalid_access_source',
                __('Źródło dostępu nie może być puste.', 'am-toolkit')
            );
        }

        $startsAt = $this->normalizeDate($context['starts_at'] ?? null);
        $expiresAt = $this->normalizeDate($context['expires_at'] ?? null);

        if (is_wp_error($startsAt)) {
            return $startsAt;
        }

        if (is_wp_error($expiresAt)) {
            return $expiresAt;
        }

        if ($startsAt !== null && $expiresAt !== null && $expiresAt <= $startsAt) {
            return new \WP_Error(
                'am_toolkit_invalid_access_period',
                __('Termin wygaśnięcia musi być późniejszy niż początek dostępu.', 'am-toolkit')
            );
        }

        $grantKey = $this->grantKey(
            (string) ($context['grant_key'] ?? ''),
            $userId,
            $resourceType,
            $resourceId,
            $sourceType,
            $sourceId
        );
        $now = current_time('mysql', true);
        $metadata = $context['metadata'] ?? [];
        $metadataJson = $metadata === [] ? null : wp_json_encode($metadata);

        if ($metadataJson === false) {
            return new \WP_Error(
                'am_toolkit_invalid_access_metadata',
                __('Nie udało się zakodować metadanych dostępu.', 'am-toolkit')
            );
        }

        $grant = [
            'user_id' => $userId,
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
            'grant_key' => $grantKey,
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'status' => 'active',
            'starts_at' => $startsAt,
            'expires_at' => $expiresAt,
            'granted_at' => $now,
            'metadata' => $metadataJson,
            'created_at' => $now,
            'updated_at' => $now,
        ];

        $stored = $this->entitlements->create($grant);

        if (is_wp_error($stored)) {
            return $stored;
        }

        if (!$stored['created']) {
            $existingGrant = $this->entitlements->findByGrantKey($grantKey);

            if ($existingGrant !== null && ($existingGrant['status'] ?? '') === 'revoked') {
                $restored = $this->entitlements->restore($grant);

                if (is_wp_error($restored)) {
                    return $restored;
                }

                if ($restored) {
                    $this->recordEvent(
                        'access.restored.' . $grantKey . '.' . wp_generate_uuid4(),
                        'access.restored',
                        $userId,
                        $resourceType,
                        $resourceId,
                        [
                            'grant_id' => $stored['id'],
                            'source_type' => $sourceType,
                            'source_id' => $sourceId,
                        ],
                        $now,
                        $requestId,
                        $grant
                    );

                    do_action('am_toolkit_access_restored', $grant, $stored['id']);
                }
            }
        }

        if ($stored['created']) {
            $this->recordEvent(
                'access.granted.' . $grantKey,
                'access.granted',
                $userId,
                $resourceType,
                $resourceId,
                [
                    'grant_id' => $stored['id'],
                    'source_type' => $sourceType,
                    'source_id' => $sourceId,
                ],
                $now,
                $requestId,
                $grant
            );

            do_action('am_toolkit_access_granted', $grant, $stored['id']);
        }

        return $stored['id'];
    }

    /** @param array{request_id?: string} $context */
    public function revoke(string $grantKey, array $context = []): bool|\WP_Error
    {
        $grantKey = $this->sanitizeGrantKey($grantKey);

        if ($grantKey === '') {
            return new \WP_Error(
                'am_toolkit_invalid_grant_key',
                __('Klucz uprawnienia nie może być pusty.', 'am-toolkit')
            );
        }

        $grant = $this->entitlements->findByGrantKey($grantKey);

        if ($grant === null || ($grant['status'] ?? '') !== 'active') {
            return false;
        }

        $now = current_time('mysql', true);
        $revoked = $this->entitlements->revoke($grantKey, $now);

        if (is_wp_error($revoked) || $revoked === false) {
            return $revoked;
        }

        $this->recordEvent(
            'access.revoked.' . $grantKey . '.' . wp_generate_uuid4(),
            'access.revoked',
            (int) $grant['user_id'],
            (string) $grant['resource_type'],
            (int) $grant['resource_id'],
            ['grant_id' => (int) $grant['id']],
            $now,
            RequestId::normalize(
                isset($context['request_id']) ? (string) $context['request_id'] : null
            ),
            $grant
        );

        do_action('am_toolkit_access_revoked', $grant);

        return true;
    }

    public function revokeSource(
        int $userId,
        string $resourceType,
        int $resourceId,
        string $sourceType,
        int $sourceId,
        array $context = []
    ): bool|\WP_Error {
        $resourceType = sanitize_key($resourceType);
        $sourceType = sanitize_key($sourceType);
        $sourceId = absint($sourceId);

        if (
            $userId <= 0
            || $resourceId <= 0
            || $resourceType === ''
            || $sourceType === ''
        ) {
            return new \WP_Error(
                'am_toolkit_invalid_access_source',
                __('Nieprawidłowy użytkownik, zasób lub źródło dostępu.', 'am-toolkit')
            );
        }

        return $this->revoke(
            $this->grantKey(
                '',
                $userId,
                $resourceType,
                $resourceId,
                $sourceType,
                $sourceId
            ),
            $context
        );
    }

    /**
     * Domain events are diagnostic history, not the transaction itself. A failed
     * diagnostic write is reported separately and must not revoke a valid grant.
     *
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $subject
     */
    private function recordEvent(
        string $eventKey,
        string $eventType,
        int $userId,
        string $objectType,
        int $objectId,
        array $payload,
        string $occurredAt,
        string $requestId,
        array $subject
    ): void {
        try {
            $result = $this->events->record(
                DomainEvent::create(
                    $eventKey,
                    $eventType,
                    $userId,
                    get_current_user_id(),
                    $objectType,
                    $objectId,
                    $payload,
                    $occurredAt,
                    $requestId
                )
            );
        } catch (\Throwable $exception) {
            $result = new \WP_Error(
                'am_toolkit_invalid_domain_event',
                $exception->getMessage()
            );
        }

        if (!is_wp_error($result)) {
            return;
        }

        $this->logger?->error(
            'Domain event could not be recorded.',
            [
                'error_code' => $result->get_error_code(),
                'event_type' => $eventType,
                'request_id' => $requestId,
            ]
        );

        do_action('am_toolkit_access_event_error', $result, $subject);
    }

    private function grantKey(
        string $providedKey,
        int $userId,
        string $resourceType,
        int $resourceId,
        string $sourceType,
        int $sourceId
    ): string {
        $providedKey = $this->sanitizeGrantKey($providedKey);

        if ($providedKey !== '') {
            return $providedKey;
        }

        return hash(
            'sha256',
            implode('|', [
                $userId,
                $resourceType,
                $resourceId,
                $sourceType,
                $sourceId,
            ])
        );
    }

    private function sanitizeGrantKey(string $grantKey): string
    {
        $grantKey = sanitize_text_field($grantKey);

        return substr($grantKey, 0, 191);
    }

    private function normalizeDate(mixed $value): string|\WP_Error|null
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            if ($value instanceof \DateTimeInterface) {
                $date = \DateTimeImmutable::createFromInterface($value);
            } else {
                $date = new \DateTimeImmutable((string) $value, wp_timezone());
            }
        } catch (\Throwable) {
            return new \WP_Error(
                'am_toolkit_invalid_access_date',
                __('Nieprawidłowa data dostępu.', 'am-toolkit')
            );
        }

        return $date
            ->setTimezone(new \DateTimeZone('UTC'))
            ->format('Y-m-d H:i:s');
    }
}
