<?php

namespace AMToolkit\Modules\Access;

use AMToolkit\Core\Diagnostics\ActivityEventQuery;
use AMToolkit\Core\Diagnostics\DomainEvent;

defined('ABSPATH') || exit;

interface ActivityEventStore
{
    /**
     * @return array{id: int, created: bool}|\WP_Error
     */
    public function record(DomainEvent $event): array|\WP_Error;

    /**
     * @return list<array<string, mixed>>|\WP_Error
     */
    public function find(ActivityEventQuery $query): array|\WP_Error;
}
