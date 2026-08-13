<?php

namespace AMToolkit\Modules\Courses\Domain;

defined('ABSPATH') || exit;

final class CourseAccessSource
{
    public const PURCHASE = 'woocommerce_order';
    public const SUBSCRIPTION = 'woocommerce_subscription';
    public const MANUAL = 'manual';
    public const DEMO = 'demo';

    private function __construct()
    {
    }
}
