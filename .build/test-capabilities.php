<?php

define('ABSPATH', __DIR__ . '/');

$GLOBALS['amt_test_options'] = [];
$GLOBALS['amt_test_roles'] = [];

final class TestRole
{
    /** @var array<string, bool> */
    private array $capabilities = [];

    public function add_cap(string $capability): void
    {
        $this->capabilities[$capability] = true;
    }

    public function has_cap(string $capability): bool
    {
        return $this->capabilities[$capability] ?? false;
    }
}

function get_option(string $name, mixed $default = false): mixed
{
    return $GLOBALS['amt_test_options'][$name] ?? $default;
}

function update_option(string $name, mixed $value, bool $autoload = true): bool
{
    $GLOBALS['amt_test_options'][$name] = $value;
    return true;
}

function get_role(string $name): ?TestRole
{
    return $GLOBALS['amt_test_roles'][$name] ?? null;
}

require_once dirname(__DIR__) . '/vendor/autoload.php';

use AMToolkit\Core\Capabilities;

$administrator = new TestRole();
$GLOBALS['amt_test_roles']['administrator'] = $administrator;

Capabilities::install();

foreach (Capabilities::all() as $capability) {
    if (!$administrator->has_cap($capability)) {
        throw new RuntimeException("Administrator is missing {$capability}.");
    }
}

$shopManager = new TestRole();
$GLOBALS['amt_test_roles']['shop_manager'] = $shopManager;

Capabilities::install();

foreach ([
    Capabilities::MANAGE_ACCESS,
    Capabilities::MANAGE_COURSES,
    Capabilities::VIEW_DIAGNOSTICS,
] as $capability) {
    if (!$shopManager->has_cap($capability)) {
        throw new RuntimeException("Late shop manager is missing {$capability}.");
    }
}

echo "OK: capabilities\n";
