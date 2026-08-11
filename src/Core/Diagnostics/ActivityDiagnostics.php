<?php

namespace AMToolkit\Core\Diagnostics;

use AMToolkit\Modules\Access\ActivityEventStore;

defined('ABSPATH') || exit;

final class ActivityDiagnostics
{
    public function __construct(private ActivityEventStore $events)
    {
    }

    /** @return array<string, mixed>|\WP_Error */
    public function inspect(ActivityEventQuery $query): array|\WP_Error
    {
        $events = $this->events->find($query);

        if (is_wp_error($events)) {
            return $events;
        }

        $keys = [];
        $duplicateKeys = [];
        $missingEventKeys = 0;
        $invalidRequestIds = 0;
        $invalidTimestamps = 0;
        $unsupportedVersions = 0;

        foreach ($events as $event) {
            $eventKey = (string) ($event['event_key'] ?? '');

            if ($eventKey === '') {
                $missingEventKeys++;
            } else {
                if (isset($keys[$eventKey])) {
                    $duplicateKeys[$eventKey] = true;
                }

                $keys[$eventKey] = true;
            }

            if (!RequestId::isValid((string) ($event['request_id'] ?? ''))) {
                $invalidRequestIds++;
            }

            if ((int) ($event['schema_version'] ?? 0) !== DomainEvent::SCHEMA_VERSION) {
                $unsupportedVersions++;
            }

            if (!$this->isValidTimestamp((string) ($event['occurred_at'] ?? ''))) {
                $invalidTimestamps++;
            }
        }

        return [
            'checked_at' => current_time('mysql', true),
            'event_count' => count($events),
            'limit_reached' => count($events) === $query->limit(),
            'duplicate_event_keys' => array_keys($duplicateKeys),
            'missing_event_keys' => $missingEventKeys,
            'invalid_request_ids' => $invalidRequestIds,
            'invalid_timestamps' => $invalidTimestamps,
            'unsupported_schema_versions' => $unsupportedVersions,
            'valid' => $duplicateKeys === []
                && $missingEventKeys === 0
                && $invalidRequestIds === 0
                && $invalidTimestamps === 0
                && $unsupportedVersions === 0,
        ];
    }

    /** @return string|\WP_Error */
    public function export(ActivityEventQuery $query): string|\WP_Error
    {
        $events = $this->events->find($query);

        if (is_wp_error($events)) {
            return $events;
        }

        $safeEvents = array_map(function (array $event): array {
            $payload = is_array($event['payload'] ?? null) ? $event['payload'] : [];

            return [
                'event_key' => (string) ($event['event_key'] ?? ''),
                'event_type' => (string) ($event['event_type'] ?? ''),
                'schema_version' => (int) ($event['schema_version'] ?? 0),
                'request_id' => (string) ($event['request_id'] ?? ''),
                'user_ref' => $this->pseudonymize((int) ($event['user_id'] ?? 0)),
                'actor_ref' => $this->pseudonymize((int) ($event['actor_id'] ?? 0)),
                'object_type' => (string) ($event['object_type'] ?? ''),
                'object_id' => (int) ($event['object_id'] ?? 0),
                'payload_keys' => array_map('strval', array_keys($payload)),
                'occurred_at' => (string) ($event['occurred_at'] ?? ''),
            ];
        }, $events);

        $encoded = wp_json_encode([
            'export_version' => 2,
            'generated_at' => current_time('mysql', true),
            'events' => $safeEvents,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        if ($encoded === false) {
            return new \WP_Error(
                'am_toolkit_diagnostic_export_failed',
                __('Nie udało się przygotować eksportu diagnostycznego.', 'am-toolkit')
            );
        }

        return $encoded;
    }

    private function pseudonymize(int $userId): string
    {
        if ($userId <= 0) {
            return 'system';
        }

        return substr(hash_hmac('sha256', (string) $userId, wp_salt('auth')), 0, 16);
    }

    private function isValidTimestamp(string $value): bool
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $value, new \DateTimeZone('UTC'));

        return $date !== false && $date->format('Y-m-d H:i:s') === $value;
    }
}
