<?php

namespace AMToolkit\Modules\Courses\Domain;

defined('ABSPATH') || exit;

final class PurchaseBatch
{
    /** @param list<PurchaseAccessRecord> $records */
    public function __construct(private array $records, private bool $hasMore)
    {
    }

    /** @return list<PurchaseAccessRecord> */
    public function records(): array
    {
        return $this->records;
    }

    public function hasMore(): bool
    {
        return $this->hasMore;
    }
}
