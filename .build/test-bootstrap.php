<?php

define('ABSPATH', __DIR__ . '/');

$GLOBALS['amt_test_actions'] = [];
$GLOBALS['amt_test_activation_hooks'] = [];

function plugin_dir_path(string $file): string
{
    return dirname($file) . DIRECTORY_SEPARATOR;
}

function plugin_dir_url(string $file): string
{
    return 'https://example.test/wp-content/plugins/am-toolkit/';
}

function register_activation_hook(string $file, callable $callback): void
{
    $GLOBALS['amt_test_activation_hooks'][] = [$file, $callback];
}

function add_action(
    string $hook,
    callable $callback,
    int $priority = 10,
    int $acceptedArgs = 1
): void {
    $GLOBALS['amt_test_actions'][] = [$hook, $callback, $priority, $acceptedArgs];
}

function sanitize_key(string $key): string
{
    return preg_replace('/[^a-z0-9_\-]/', '', strtolower($key)) ?? '';
}

require dirname(__DIR__) . '/am-toolkit.php';

if (count($GLOBALS['amt_test_activation_hooks']) !== 1) {
    throw new RuntimeException('Plugin must register exactly one activation hook.');
}

$hooks = array_column($GLOBALS['amt_test_actions'], 0);

if ($hooks !== ['plugins_loaded', 'plugins_loaded', 'init']) {
    throw new RuntimeException(
        'Unexpected bootstrap hooks: ' . implode(', ', $hooks)
    );
}

if (!class_exists(AMToolkit\Core\Plugin::class)) {
    throw new RuntimeException('Composer did not autoload the Plugin class.');
}

echo "OK: plugin bootstrap\n";
