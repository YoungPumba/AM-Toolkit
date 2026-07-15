<?php

namespace AMToolkit\Settings;

if (!defined('ABSPATH')) {
    exit;
}

final class Notifications
{
    public const OPTION_NAME = 'am_toolkit_notification_settings';

    public static function defaults(): array
    {
        return [
            'added_enabled'   => 1,
            'added_title'     => __('Dodano do koszyka', 'am-toolkit'),
            'added_message'   => __('Produkt „{product_name}” został dodany do koszyka.', 'am-toolkit'),
            'removed_enabled' => 1,
            'removed_title'   => __('Usunięto z koszyka', 'am-toolkit'),
            'removed_message' => __('Produkt „{product_name}” został usunięty z koszyka.', 'am-toolkit'),
            'cart_action'     => __('Przejdź do koszyka →', 'am-toolkit'),
            'duration'        => 4000,
        ];
    }

    public static function get(): array
    {
        $stored = get_option(self::OPTION_NAME, []);

        return wp_parse_args(is_array($stored) ? $stored : [], self::defaults());
    }

    public static function sanitize($input): array
    {
        $input = is_array($input) ? $input : [];
        $defaults = self::defaults();
        $duration = absint($input['duration'] ?? $defaults['duration']);

        return [
            'added_enabled'   => empty($input['added_enabled']) ? 0 : 1,
            'added_title'     => self::sanitizeRequiredText($input['added_title'] ?? '', $defaults['added_title']),
            'added_message'   => self::sanitizeRequiredTextarea($input['added_message'] ?? '', $defaults['added_message']),
            'removed_enabled' => empty($input['removed_enabled']) ? 0 : 1,
            'removed_title'   => self::sanitizeRequiredText($input['removed_title'] ?? '', $defaults['removed_title']),
            'removed_message' => self::sanitizeRequiredTextarea($input['removed_message'] ?? '', $defaults['removed_message']),
            'cart_action'     => self::sanitizeRequiredText($input['cart_action'] ?? '', $defaults['cart_action']),
            'duration'        => max(1000, min(15000, $duration)),
        ];
    }

    public static function formatMessage(string $template, string $productName): string
    {
        return str_replace('{product_name}', $productName, $template);
    }

    private static function sanitizeRequiredText(string $value, string $fallback): string
    {
        $value = sanitize_text_field($value);

        return '' !== $value ? $value : $fallback;
    }

    private static function sanitizeRequiredTextarea(string $value, string $fallback): string
    {
        $value = sanitize_textarea_field($value);

        return '' !== $value ? $value : $fallback;
    }
}
