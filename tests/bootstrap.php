<?php

declare(strict_types=1);

define('ABSPATH', dirname(__DIR__) . '/');

final class WP_Error
{
    public function __construct(
        private string $code = '',
        private string $message = '',
        private mixed $data = null
    ) {
    }

    public function get_error_code(): string
    {
        return $this->code;
    }

    public function get_error_message(): string
    {
        return $this->message;
    }

    public function get_error_data(): mixed
    {
        return $this->data;
    }
}

function sanitize_key(string $value): string
{
    return preg_replace('/[^a-z0-9_\-]/', '', strtolower($value)) ?? '';
}

function sanitize_text_field(string $value): string
{
    return trim(strip_tags($value));
}

function sanitize_file_name(string $value): string
{
    $value = preg_replace('/[^A-Za-z0-9._-]+/', '-', $value) ?? '';

    return trim($value, '-');
}

function absint(mixed $value): int
{
    return abs((int) $value);
}

function wp_json_encode(mixed $value, int $flags = 0, int $depth = 512): string|false
{
    return json_encode($value, $flags, $depth);
}

function is_wp_error(mixed $value): bool
{
    return $value instanceof WP_Error;
}

function current_time(string $type, bool $gmt = false): string
{
    return '2026-08-10 10:00:00';
}

function wp_salt(string $scheme = 'auth'): string
{
    return 'am-toolkit-tests-' . $scheme;
}

function get_current_user_id(): int
{
    return 1;
}

function __(string $text, string $domain = 'default'): string
{
    return $text;
}

function esc_url(string $url): string
{
    return htmlspecialchars($url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function wp_enqueue_style(string $handle): void
{
    $GLOBALS['amt_test_enqueued_styles'][] = $handle;
}

function wp_enqueue_script(string $handle): void
{
    $GLOBALS['amt_test_enqueued_scripts'][] = $handle;
}

/** @return list<string> */
function wc_get_is_paid_statuses(): array
{
    return ['processing', 'completed'];
}

/** @param string|array<int, string> $queries */
function dbDelta(string|array $queries = '', bool $execute = true): array
{
    $handler = $GLOBALS['amt_test_dbdelta_handler'] ?? null;

    if (is_callable($handler)) {
        return $handler($queries, $execute);
    }

    return [];
}

require_once dirname(__DIR__) . '/vendor/autoload.php';
