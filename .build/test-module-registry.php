<?php

define('ABSPATH', __DIR__ . '/');

$GLOBALS['amt_test_options'] = [];

function sanitize_key(string $value): string
{
    return preg_replace('/[^a-z0-9_\-]/', '', strtolower($value)) ?? '';
}

function get_option(string $name, mixed $default = false): mixed
{
    return $GLOBALS['amt_test_options'][$name] ?? $default;
}

function apply_filters(string $hook, mixed $value): mixed
{
    return $value;
}

function do_action(string $hook, mixed ...$args): void
{
}

require_once dirname(__DIR__) . '/vendor/autoload.php';

use AMToolkit\Core\FeatureFlags;
use AMToolkit\Core\ModuleInterface;
use AMToolkit\Core\ModuleRegistry;

final class TestModule implements ModuleInterface
{
    /** @var list<string> */
    private array $bootOrder;

    public function __construct(
        private string $moduleId,
        private array $requires,
        array &$bootOrder,
        private bool $available = true
    ) {
        $this->bootOrder =& $bootOrder;
    }

    public function id(): string
    {
        return $this->moduleId;
    }

    public function dependencies(): array
    {
        return $this->requires;
    }

    public function isAvailable(): bool
    {
        return $this->available;
    }

    public function boot(): void
    {
        $this->bootOrder[] = $this->moduleId;
    }
}

function assertSameValue(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException(
            $message . '\nExpected: ' . var_export($expected, true)
            . '\nActual: ' . var_export($actual, true)
        );
    }
}

$order = [];
$registry = new ModuleRegistry(new FeatureFlags());
$registry->register(new TestModule('account', ['access'], $order));
$registry->register(new TestModule('core', [], $order));
$registry->register(new TestModule('access', ['core'], $order));
$registry->bootAll();

assertSameValue(['core', 'access', 'account'], $order, 'Dependencies must boot first.');
assertSameValue('booted', $registry->statuses()['account'], 'Account should boot.');

$GLOBALS['amt_test_options']['am_toolkit_feature_flags'] = ['access' => false];
$order = [];
$disabled = new ModuleRegistry(new FeatureFlags());
$disabled->register(new TestModule('core', [], $order));
$disabled->register(new TestModule('access', ['core'], $order));
$disabled->register(new TestModule('account', ['access'], $order));
$disabled->bootAll();

assertSameValue(['core'], $order, 'Disabled dependency must prevent dependent boot.');
assertSameValue(
    'skipped:dependency:access',
    $disabled->statuses()['account'],
    'Dependent module should expose its skip reason.'
);

$order = [];
$coursesDisabled = new ModuleRegistry(new FeatureFlags());
$coursesDisabled->register(new TestModule('core', [], $order));
$coursesDisabled->register(new TestModule('access', ['core'], $order));
$coursesDisabled->register(new TestModule('courses', ['core', 'access'], $order));
$coursesDisabled->bootAll();

assertSameValue(
    'skipped:disabled',
    $coursesDisabled->statuses()['courses'],
    'Courses must stay disabled until its feature flag is enabled.'
);

$GLOBALS['amt_test_options']['am_toolkit_feature_flags'] = ['courses' => true];
$order = [];
$coursesEnabled = new ModuleRegistry(new FeatureFlags());
$coursesEnabled->register(new TestModule('core', [], $order));
$coursesEnabled->register(new TestModule('access', ['core'], $order));
$coursesEnabled->register(new TestModule('courses', ['core', 'access'], $order));
$coursesEnabled->bootAll();

assertSameValue(
    ['core', 'access', 'courses'],
    $order,
    'Enabled Courses module must boot after Core and Access.'
);

echo "OK: module registry\n";
