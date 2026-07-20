<?php

namespace AMToolkit\Settings;

if (!defined('ABSPATH')) {
    exit;
}

final class CheckoutNotice
{
    public const OPTION_NAME = 'am_toolkit_checkout_notice_settings';

    public static function defaults(): array
    {
        return [
            'font_family'  => 'Poppins',
            'font_size'    => 14,
            'font_weight'  => 400,
            'link_weight'  => 500,
            'text_color'   => '#171717',
            'link_color'   => '#f176a4',
            'icon_color'   => '#f176a4',
            'background'   => '#ffffff',
            'border_color' => '#dedede',
            'border_width' => 1,
            'radius'       => 25,
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

        return [
            'font_family'  => self::sanitizeFontFamily($input['font_family'] ?? $defaults['font_family']),
            'font_size'    => self::sanitizeInteger($input['font_size'] ?? $defaults['font_size'], 11, 24),
            'font_weight'  => self::sanitizeWeight($input['font_weight'] ?? $defaults['font_weight'], 400),
            'link_weight'  => self::sanitizeWeight($input['link_weight'] ?? $defaults['link_weight'], 500),
            'text_color'   => self::sanitizeColor($input['text_color'] ?? '', $defaults['text_color']),
            'link_color'   => self::sanitizeColor($input['link_color'] ?? '', $defaults['link_color']),
            'icon_color'   => self::sanitizeColor($input['icon_color'] ?? '', $defaults['icon_color']),
            'background'   => self::sanitizeColor($input['background'] ?? '', $defaults['background']),
            'border_color' => self::sanitizeColor($input['border_color'] ?? '', $defaults['border_color']),
            'border_width' => self::sanitizeInteger($input['border_width'] ?? $defaults['border_width'], 0, 5),
            'radius'       => self::sanitizeInteger($input['radius'] ?? $defaults['radius'], 0, 50),
        ];
    }

    public static function inlineCss(): string
    {
        $settings = self::get();
        $fontFamily = 'Poppins' === $settings['font_family']
            ? '"Poppins", sans-serif'
            : 'inherit';

        return sprintf(
            '.woocommerce-checkout .woocommerce-NoticeGroup-checkout .woocommerce-error{' .
            '--amt-checkout-font-family:%1$s;' .
            '--amt-checkout-font-size:%2$dpx;' .
            '--amt-checkout-font-weight:%3$d;' .
            '--amt-checkout-link-weight:%4$d;' .
            '--amt-checkout-text-color:%5$s;' .
            '--amt-checkout-link-color:%6$s;' .
            '--amt-checkout-icon-color:%7$s;' .
            '--amt-checkout-background:%8$s;' .
            '--amt-checkout-border-color:%9$s;' .
            '--amt-checkout-border-width:%10$dpx;' .
            '--amt-checkout-radius:%11$dpx;' .
            '}',
            $fontFamily,
            $settings['font_size'],
            $settings['font_weight'],
            $settings['link_weight'],
            $settings['text_color'],
            $settings['link_color'],
            $settings['icon_color'],
            $settings['background'],
            $settings['border_color'],
            $settings['border_width'],
            $settings['radius']
        );
    }

    private static function sanitizeFontFamily(string $value): string
    {
        return in_array($value, ['Poppins', 'inherit'], true) ? $value : 'Poppins';
    }

    private static function sanitizeWeight($value, int $fallback): int
    {
        $weight = absint($value);

        return in_array($weight, [300, 400, 500, 600, 700], true) ? $weight : $fallback;
    }

    private static function sanitizeInteger($value, int $minimum, int $maximum): int
    {
        return max($minimum, min($maximum, absint($value)));
    }

    private static function sanitizeColor(string $value, string $fallback): string
    {
        $color = sanitize_hex_color($value);

        return is_string($color) ? $color : $fallback;
    }
}
