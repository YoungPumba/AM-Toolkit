<?php

namespace AMToolkit\Core;

defined('ABSPATH') || exit;

interface MigrationInterface
{
    public function up(): bool;
}
