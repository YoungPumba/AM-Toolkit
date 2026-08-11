<?php

namespace AMToolkit\Core\Diagnostics;

defined('ABSPATH') || exit;

final class DomainEvent
{
    public const SCHEMA_VERSION = 1;

    private const MAX_PAYLOAD_BYTES = 8192;

    private const MAX_PAYLOAD_ITEMS = 24;

    private const MAX_PAYLOAD_DEPTH = 3;

    /** @var array<string, bool> */
    private const ACCESS_PAYLOAD_KEYS = [
        'grant_id' => true,
        'source_type' => true,
        'source_id' => true,
    ];

    /** @param array<string, mixed> $payload */
    private function __construct(
        private string $eventKey,
        private string $eventType,
        private int $userId,
        private int $actorId,
        private string $objectType,
        private int $objectId,
        private array $payload,
        private string $occurredAt,
        private string $requestId
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public static function create(
        string $eventKey,
        string $eventType,
        int $userId,
        int $actorId,
        string $objectType,
        int $objectId,
        array $payload,
        string $occurredAt,
        ?string $requestId = null
    ): self {
        $eventKey = self::limitedText($eventKey, 191);
        $eventType = EventType::normalize($eventType);
        $objectType = sanitize_key($objectType);

        if ($eventKey === '' || $eventType === '' || $objectType === '') {
            throw new \InvalidArgumentException('Event key, type and object type are required.');
        }

        if (!self::isValidTimestamp($occurredAt)) {
            throw new \InvalidArgumentException('The event timestamp must use the UTC MySQL format.');
        }

        $payload = self::sanitizePayload($eventType, $payload);
        $encodedPayload = wp_json_encode($payload);

        if ($encodedPayload === false || strlen($encodedPayload) > self::MAX_PAYLOAD_BYTES) {
            throw new \LengthException('Domain event payload exceeds the safe size limit.');
        }

        return new self(
            $eventKey,
            $eventType,
            max(0, $userId),
            max(0, $actorId),
            $objectType,
            max(0, $objectId),
            $payload,
            $occurredAt,
            RequestId::normalize($requestId)
        );
    }

    /** @return array<string, mixed> */
    public function toRecord(): array
    {
        return [
            'event_key' => $this->eventKey,
            'event_type' => $this->eventType,
            'schema_version' => self::SCHEMA_VERSION,
            'request_id' => $this->requestId,
            'user_id' => $this->userId,
            'actor_id' => $this->actorId,
            'object_type' => $this->objectType,
            'object_id' => $this->objectId,
            'payload' => $this->payload,
            'occurred_at' => $this->occurredAt,
        ];
    }

    public function requestId(): string
    {
        return $this->requestId;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private static function sanitizePayload(string $eventType, array $payload): array
    {
        if (str_starts_with($eventType, 'access.')) {
            $payload = array_intersect_key($payload, self::ACCESS_PAYLOAD_KEYS);
        }

        if (count($payload) > self::MAX_PAYLOAD_ITEMS) {
            throw new \LengthException('Domain event payload contains too many fields.');
        }

        return self::sanitizePayloadLevel($payload, 1);
    }

    /**
     * @param array<array-key, mixed> $payload
     * @return array<array-key, mixed>
     */
    private static function sanitizePayloadLevel(array $payload, int $depth): array
    {
        if ($depth > self::MAX_PAYLOAD_DEPTH) {
            throw new \LengthException('Domain event payload is nested too deeply.');
        }

        if (count($payload) > self::MAX_PAYLOAD_ITEMS) {
            throw new \LengthException('Domain event payload contains too many fields.');
        }

        $safe = [];

        foreach ($payload as $key => $value) {
            $safeKey = is_int($key) ? $key : sanitize_key((string) $key);

            if ($safeKey === '') {
                continue;
            }

            if (is_array($value)) {
                $safe[$safeKey] = self::sanitizePayloadLevel($value, $depth + 1);
                continue;
            }

            if (is_bool($value) || is_int($value) || is_float($value) || $value === null) {
                $safe[$safeKey] = $value;
                continue;
            }

            if (is_string($value)) {
                $safe[$safeKey] = self::limitedText($value, 512);
                continue;
            }

            throw new \InvalidArgumentException('Domain event payload contains an unsupported value.');
        }

        return $safe;
    }

    private static function limitedText(string $value, int $length): string
    {
        $value = sanitize_text_field($value);

        return function_exists('mb_substr')
            ? mb_substr($value, 0, $length)
            : substr($value, 0, $length);
    }

    private static function isValidTimestamp(string $value): bool
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $value, new \DateTimeZone('UTC'));

        return $date !== false && $date->format('Y-m-d H:i:s') === $value;
    }
}
