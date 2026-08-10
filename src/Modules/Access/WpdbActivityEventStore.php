<?php

namespace AMToolkit\Modules\Access;

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

    public function record(array $event): array|\WP_Error
    {
        $sql = $this->database->prepare(
            "INSERT INTO {$this->table} (
                event_key, event_type, user_id, actor_id,
                object_type, object_id, payload, occurred_at
            ) VALUES (
                %s, %s, %d, %d,
                %s, %d, NULLIF(%s, ''), %s
            ) ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)",
            $event['event_key'],
            $event['event_type'],
            $event['user_id'],
            $event['actor_id'],
            $event['object_type'],
            $event['object_id'],
            $event['payload'] ?? '',
            $event['occurred_at']
        );

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
}
