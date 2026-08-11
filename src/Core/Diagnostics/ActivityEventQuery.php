<?php

namespace AMToolkit\Core\Diagnostics;

defined('ABSPATH') || exit;

final class ActivityEventQuery
{
    public function __construct(
        private ?string $requestId = null,
        private ?int $userId = null,
        private ?string $objectType = null,
        private ?int $objectId = null,
        private ?string $eventType = null,
        private int $limit = 100
    ) {
        $requestId = $requestId === null ? null : strtoupper(trim($requestId));
        $userId = $userId === null || $userId <= 0 ? null : $userId;
        $objectType = $objectType === null ? null : sanitize_key($objectType);
        $objectId = $objectId === null || $objectId <= 0 ? null : $objectId;
        $eventType = $eventType === null ? null : EventType::normalize($eventType);

        $this->requestId = $requestId !== null && RequestId::isValid($requestId) ? $requestId : null;
        $this->userId = $userId;
        $this->objectType = $objectType !== '' ? $objectType : null;
        $this->objectId = $objectId;
        $this->eventType = $eventType !== '' ? $eventType : null;
        $this->limit = max(1, min(200, $limit));
    }

    public function requestId(): ?string
    {
        return $this->requestId;
    }

    public function userId(): ?int
    {
        return $this->userId;
    }

    public function objectType(): ?string
    {
        return $this->objectType;
    }

    public function objectId(): ?int
    {
        return $this->objectId;
    }

    public function eventType(): ?string
    {
        return $this->eventType;
    }

    public function limit(): int
    {
        return $this->limit;
    }
}
