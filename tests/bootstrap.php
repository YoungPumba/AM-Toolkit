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

function __(string $text, string $domain = 'default'): string
{
    return $text;
}

require_once dirname(__DIR__) . '/vendor/autoload.php';
