<?php

define('ABSPATH', __DIR__ . '/');

final class WP_Error
{
    public function __construct(
        public string $code,
        public string $message,
        public mixed $data = null
    ) {
    }
}

function __(string $text): string
{
    return $text;
}

function sanitize_key(string $value): string
{
    return preg_replace('/[^a-z0-9_\-]/', '', strtolower($value)) ?? '';
}

function sanitize_text_field(string $value): string
{
    return trim(strip_tags($value));
}

function absint(mixed $value): int
{
    return abs((int) $value);
}

function current_time(string $type, bool $gmt = false): string
{
    return gmdate('Y-m-d H:i:s');
}

function wp_timezone(): DateTimeZone
{
    return new DateTimeZone('Europe/Warsaw');
}

function wp_json_encode(mixed $value): string|false
{
    return json_encode($value);
}

function is_wp_error(mixed $value): bool
{
    return $value instanceof WP_Error;
}

function get_current_user_id(): int
{
    return 99;
}

function wp_generate_uuid4(): string
{
    static $sequence = 0;

    $sequence++;

    return '00000000-0000-4000-8000-' . str_pad((string) $sequence, 12, '0', STR_PAD_LEFT);
}

function apply_filters(string $hook, mixed $value): mixed
{
    return $value;
}

function do_action(string $hook, mixed ...$args): void
{
}

require_once dirname(__DIR__) . '/vendor/autoload.php';

use AMToolkit\Modules\Access\AccessManager;
use AMToolkit\Modules\Access\ActivityEventStore;
use AMToolkit\Modules\Access\EntitlementStore;
use AMToolkit\Core\Diagnostics\ActivityEventQuery;
use AMToolkit\Core\Diagnostics\DomainEvent;
use AMToolkit\Core\Diagnostics\RequestId;

final class MemoryEntitlements implements EntitlementStore
{
    public array $grants = [];

    public bool $failSourceRead = false;

    private int $nextId = 1;

    public function create(array $grant): array|WP_Error
    {
        if (isset($this->grants[$grant['grant_key']])) {
            return [
                'id' => $this->grants[$grant['grant_key']]['id'],
                'created' => false,
            ];
        }

        $grant['id'] = $this->nextId++;
        $this->grants[$grant['grant_key']] = $grant;

        return ['id' => $grant['id'], 'created' => true];
    }

    public function hasActiveGrant(
        int $userId,
        string $resourceType,
        int $resourceId,
        string $at
    ): bool {
        foreach ($this->grants as $grant) {
            if (
                $grant['user_id'] === $userId
                && $grant['resource_type'] === $resourceType
                && $grant['resource_id'] === $resourceId
                && $grant['status'] === 'active'
                && ($grant['starts_at'] === null || $grant['starts_at'] <= $at)
                && ($grant['expires_at'] === null || $grant['expires_at'] > $at)
            ) {
                return true;
            }
        }

        return false;
    }

    public function findByGrantKey(string $grantKey): ?array
    {
        return $this->grants[$grantKey] ?? null;
    }

    public function findActiveBySource(string $sourceType, int $sourceId): array|WP_Error
    {
        if ($this->failSourceRead) {
            return new WP_Error('source_read_failed', 'Source read failed');
        }

        return array_values(array_filter(
            $this->grants,
            static fn (array $grant): bool => $grant['source_type'] === $sourceType
                && $grant['source_id'] === $sourceId
                && $grant['status'] === 'active'
        ));
    }

    public function revoke(string $grantKey, string $revokedAt): bool|WP_Error
    {
        if (!isset($this->grants[$grantKey])) {
            return false;
        }

        $this->grants[$grantKey]['status'] = 'revoked';
        $this->grants[$grantKey]['revoked_at'] = $revokedAt;

        return true;
    }

    public function restore(array $grant): bool|WP_Error
    {
        $grantKey = $grant['grant_key'];

        if (
            !isset($this->grants[$grantKey])
            || $this->grants[$grantKey]['status'] !== 'revoked'
        ) {
            return false;
        }

        $grant['id'] = $this->grants[$grantKey]['id'];
        $this->grants[$grantKey] = $grant;

        return true;
    }
}

final class MemoryEvents implements ActivityEventStore
{
    public array $events = [];

    public function record(DomainEvent $event): array|WP_Error
    {
        $event = $event->toRecord();

        if (isset($this->events[$event['event_key']])) {
            return [
                'id' => $this->events[$event['event_key']]['id'],
                'created' => false,
            ];
        }

        $event['id'] = count($this->events) + 1;
        $this->events[$event['event_key']] = $event;

        return ['id' => $event['id'], 'created' => true];
    }

    public function find(ActivityEventQuery $query): array|WP_Error
    {
        return array_slice(array_values($this->events), 0, $query->limit());
    }
}

function assertSameValue(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException(
            $message . ': expected ' . var_export($expected, true)
            . ', got ' . var_export($actual, true)
        );
    }
}

$entitlements = new MemoryEntitlements();
$events = new MemoryEvents();
$access = new AccessManager($entitlements, $events);

$orderGrant = [
    'source_type' => 'order_item',
    'source_id' => 501,
    'metadata' => ['order_id' => 200],
];

$firstId = $access->grant(7, 'course', 42, $orderGrant);
$duplicateId = $access->grant(7, 'course', 42, $orderGrant);

assertSameValue(1, $firstId, 'Pierwszy grant powinien zostać zapisany');
assertSameValue($firstId, $duplicateId, 'Ponowne zdarzenie nie może dublować grantu');
assertSameValue(1, count($entitlements->grants), 'Grant musi być idempotentny');
assertSameValue(1, count($events->events), 'Zdarzenie nadania musi być idempotentne');
$firstEvent = array_values($events->events)[0];
assertSameValue(DomainEvent::SCHEMA_VERSION, $firstEvent['schema_version'], 'Zdarzenie ma wersję kontraktu');
assertSameValue(true, RequestId::isValid($firstEvent['request_id']), 'Zdarzenie ma poprawny request ID');
assertSameValue(true, $access->userHasAccess(7, 'course', 42), 'Aktywny grant daje dostęp');

$manualGrant = [
    'source_type' => 'manual',
    'source_id' => 99,
];
$manualId = $access->grant(7, 'course', 42, $manualGrant);

assertSameValue(2, $manualId, 'Drugie źródło powinno utworzyć osobny grant');

assertSameValue(
    true,
    $access->revokeSource(7, 'course', 42, 'order_item', 501),
    'Grant zakupu powinien dać się odebrać'
);
assertSameValue(
    true,
    $access->userHasAccess(7, 'course', 42),
    'Drugie aktywne źródło musi zachować dostęp'
);
assertSameValue(
    $firstId,
    $access->grant(7, 'course', 42, $orderGrant),
    'The restored grant must keep its original ID'
);
assertSameValue(2, count($entitlements->grants), 'Restore must not create a new grant');
assertSameValue(true, $access->userHasAccess(7, 'course', 42), 'Restored grant gives access');
assertSameValue(
    true,
    $access->revokeSource(7, 'course', 42, 'order_item', 501),
    'Restored grant can be revoked again'
);
assertSameValue(
    true,
    $access->revokeSource(7, 'course', 42, 'manual', 99),
    'Grant ręczny powinien dać się odebrać'
);
assertSameValue(false, $access->userHasAccess(7, 'course', 42), 'Brak aktywnych grantów odbiera dostęp');

$access->grant(9, 'course', 42, ['source_type' => 'subscription', 'source_id' => 700]);
$access->grant(9, 'course', 43, ['source_type' => 'subscription', 'source_id' => 700]);
$access->grant(9, 'course', 42, ['source_type' => 'order', 'source_id' => 502]);
$revocationRequestId = 'AM-20260810-123456ABCDEF';
$eventCountBeforeSourceRevoke = count($events->events);

assertSameValue(
    2,
    $access->revokeAllSource('subscription', 700, ['request_id' => $revocationRequestId]),
    'Wygaszenie subskrypcji powinno cofnąć wszystkie jej granty'
);
assertSameValue(true, $access->userHasAccess(9, 'course', 42), 'Zakup zachowuje dostęp po wygaśnięciu subskrypcji');
assertSameValue(false, $access->userHasAccess(9, 'course', 43), 'Kurs bez innego źródła traci dostęp');
$sourceRevokeEvents = array_slice(array_values($events->events), $eventCountBeforeSourceRevoke);
assertSameValue(2, count($sourceRevokeEvents), 'Każdy cofnięty grant ma zdarzenie audytowe');
assertSameValue(
    [$revocationRequestId, $revocationRequestId],
    array_column($sourceRevokeEvents, 'request_id'),
    'Jedno wygaszenie subskrypcji używa wspólnego request ID'
);
$entitlements->failSourceRead = true;
$failedSourceRevoke = $access->revokeAllSource('subscription', 701);
assertSameValue(true, is_wp_error($failedSourceRevoke), 'Błąd odczytu źródła nie może udawać sukcesu');
$entitlements->failSourceRead = false;

$expired = $access->grant(8, 'course', 42, [
    'source_type' => 'migration',
    'source_id' => 8,
    'expires_at' => '2020-01-01 00:00:00',
]);

assertSameValue(false, is_wp_error($expired), 'Wygasły grant nadal jest poprawnym rekordem');
assertSameValue(false, $access->userHasAccess(8, 'course', 42), 'Wygasły grant nie daje dostępu');

echo "AM Access Core: OK\n";
