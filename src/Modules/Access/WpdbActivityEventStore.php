<?php

namespace AMToolkit\Modules\Access;

use AMToolkit\Core\Diagnostics\ActivityEventQuery;
use AMToolkit\Core\Diagnostics\DomainEvent;

defined('ABSPATH') || exit;

final class WpdbActivityEventStore implements ActivityEventStore
{
    private \wpdb $database;

    private string $table;

    public function __construct(?\wpdb $database = null, ?string $table = null)
    {
        global $wpdb;

        $this->database = $database ?? $wpdb;
        $this->table = $table ?? AccessSchema::eventsTable();
    }

    public function record(DomainEvent $event): array|\WP_Error
    {
        $record = $event->toRecord();
        $payload = wp_json_encode($record['payload']);

        if ($payload === false) {
            return new \WP_Error(
                'am_toolkit_event_payload_encode_failed',
                __('Nie udało się zakodować danych zdarzenia AM Toolkit.', 'am-toolkit')
            );
        }

        $sql = $this->database->prepare(
            "INSERT INTO {$this->table} (
                event_key, event_type, user_id, actor_id,
                object_type, object_id, schema_version, request_id,
                payload, occurred_at
            ) VALUES (
                %s, %s, %d, %d,
                %s, %d, %d, %s,
                NULLIF(%s, ''), %s
            ) ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)",
            $record['event_key'],
            $record['event_type'],
            $record['user_id'],
            $record['actor_id'],
            $record['object_type'],
            $record['object_id'],
            $record['schema_version'],
            $record['request_id'],
            $payload,
            $record['occurred_at']
        );

        if (!is_string($sql)) {
            return new \WP_Error(
                'am_toolkit_event_query_prepare_failed',
                __('Nie udało się przygotować zapytania zapisującego zdarzenie AM Toolkit.', 'am-toolkit')
            );
        }

        $result = $this->database->query($sql);

        if ($result === false) {
            return new \WP_Error(
                'am_toolkit_event_write_failed',
                __('Nie udało się zapisać zdarzenia AM Toolkit.', 'am-toolkit'),
                ['database_error' => $this->database->last_error]
            );
        }

        return [
            'id' => (int) $this->database->insert_id,
            'created' => $result === 1,
        ];
    }

    public function find(ActivityEventQuery $query): array|\WP_Error
    {
        $where = ['1 = 1'];
        $values = [];

        $filters = [
            'request_id = %s' => $query->requestId(),
            'user_id = %d' => $query->userId(),
            'object_type = %s' => $query->objectType(),
            'object_id = %d' => $query->objectId(),
            'event_type = %s' => $query->eventType(),
        ];

        foreach ($filters as $clause => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            $where[] = $clause;
            $values[] = $value;
        }

        $values[] = $query->limit();
        $sql = "SELECT id, event_key, event_type, user_id, actor_id,
                    object_type, object_id, schema_version, request_id,
                    payload, occurred_at
                FROM {$this->table}
                WHERE " . implode(' AND ', $where) . '
                ORDER BY occurred_at DESC, id DESC
                LIMIT %d';
        $prepared = $this->database->prepare($sql, ...$values);

        if (!is_string($prepared)) {
            return new \WP_Error(
                'am_toolkit_event_query_prepare_failed',
                __('Nie udało się przygotować zapytania odczytującego zdarzenia AM Toolkit.', 'am-toolkit')
            );
        }

        $rows = $this->database->get_results($prepared, ARRAY_A);

        if ($rows === null && $this->database->last_error !== '') {
            return new \WP_Error(
                'am_toolkit_event_read_failed',
                __('Nie udało się odczytać zdarzeń AM Toolkit.', 'am-toolkit'),
                ['database_error' => $this->database->last_error]
            );
        }

        return array_map(static function (array $row): array {
            $decoded = json_decode((string) ($row['payload'] ?? ''), true);
            $row['payload'] = is_array($decoded) ? $decoded : [];
            $row['id'] = (int) $row['id'];
            $row['user_id'] = (int) $row['user_id'];
            $row['actor_id'] = (int) $row['actor_id'];
            $row['object_id'] = (int) $row['object_id'];
            $row['schema_version'] = (int) $row['schema_version'];

            return $row;
        }, $rows ?: []);
    }
}
