<?php

namespace AMToolkit\Modules\Access;

defined('ABSPATH') || exit;

interface ActivityEventStore
{
    /**
     * @return array{id: int, created: bool}|\WP_Error
     */
    public function record(array $event): array|\WP_Error;
}
