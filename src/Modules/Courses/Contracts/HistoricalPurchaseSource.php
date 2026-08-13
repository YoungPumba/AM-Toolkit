<?php

namespace AMToolkit\Modules\Courses\Contracts;

use AMToolkit\Modules\Courses\Domain\PurchaseBatch;

defined('ABSPATH') || exit;

interface HistoricalPurchaseSource
{
    public function fetch(int $page, int $limit): PurchaseBatch|\WP_Error;
}
