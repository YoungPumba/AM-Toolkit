<?php

namespace AMToolkit\Modules\Courses\Domain;

defined('ABSPATH') || exit;

final class PurchaseAccessRecord
{
    /** @var list<int> */
    private array $productIds;

    /** @param list<int> $productIds @param array<string, mixed> $metadata */
    public function __construct(
        private int $userId,
        private int $orderId,
        array $productIds,
        private array $metadata = []
    ) {
        $this->productIds = array_values(array_unique(array_filter(
            array_map('absint', $productIds),
            static fn (int $id): bool => $id > 0
        )));

        if ($this->userId <= 0 || $this->orderId <= 0) {
            throw new \InvalidArgumentException('Purchase user and order IDs must be positive.');
        }
    }

    public function userId(): int
    {
        return $this->userId;
    }

    public function orderId(): int
    {
        return $this->orderId;
    }

    /** @return list<int> */
    public function productIds(): array
    {
        return $this->productIds;
    }

    /** @return array<string, mixed> */
    public function metadata(): array
    {
        return $this->metadata;
    }
}
