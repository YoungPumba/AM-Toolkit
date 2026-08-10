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

function update_option(string $name, mixed $value, bool $autoload = true): bool
{
    $GLOBALS['amt_test_options'][$name] = $value;
    return true;
}

function do_action(string $hook, mixed ...$args): void
{
}

require_once dirname(__DIR__) . '/vendor/autoload.php';

use AMToolkit\Core\MigrationInterface;
use AMToolkit\Core\MigrationRunner;

final class TestMigration implements MigrationInterface
{
    public int $runs = 0;

    public function __construct(private bool $result = true)
    {
    }

    public function up(): bool
    {
        $this->runs++;
        return $this->result;
    }
}

function assertMigration(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$first = new TestMigration();
$second = new TestMigration();
$runner = new MigrationRunner();
$runner->run('courses', [1 => $first, 2 => $second]);
$runner->run('courses', [1 => $first, 2 => $second]);

assertMigration($first->runs === 1, 'Migration 1 must be idempotent.');
assertMigration($second->runs === 1, 'Migration 2 must be idempotent.');
assertMigration(
    $GLOBALS['amt_test_options']['am_toolkit_schema_courses'] === 2,
    'Module schema version should advance after verification.'
);

$failed = new TestMigration(false);

try {
    $runner->run('account', [1 => $failed]);
    throw new RuntimeException('Failed migration should throw.');
} catch (RuntimeException $error) {
    assertMigration($failed->runs === 1, 'Failed migration should run once.');
    assertMigration(
        !isset($GLOBALS['amt_test_options']['am_toolkit_schema_account']),
        'Failed migration must not advance the schema version.'
    );
}

echo "OK: migrations\n";
