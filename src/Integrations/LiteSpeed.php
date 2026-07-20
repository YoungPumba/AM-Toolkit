<?php

namespace AMToolkit\Integrations;

if (!defined('ABSPATH')) {
    exit;
}

final class LiteSpeed
{
    public function boot(): void
    {
        add_filter('litespeed_optimize_js_excludes', [$this, 'excludeCriticalScripts']);
        add_filter('litespeed_optm_js_defer_exc', [$this, 'excludeCriticalScripts']);
        add_filter('litespeed_optm_gm_js_exc', [$this, 'excludeCriticalScripts']);
        add_filter('script_loader_tag', [$this, 'markCriticalScript'], 20, 3);
    }

    public function excludeCriticalScripts(array $excludes): array
    {
        $criticalScripts = [
            '/wp-includes/js/jquery/jquery.min.js',
            '/wp-includes/js/jquery/jquery-migrate.min.js',
            '/woocommerce/assets/js/frontend/add-to-cart',
            '/woocommerce/assets/js/frontend/cart-fragments',
            '/woocommerce/assets/js/frontend/cart.min.js',
            '/am-toolkit/assets/js/core.js',
            '/am-toolkit/assets/js/toast.js',
            '/am-toolkit/assets/js/woocommerce-toast.js',
            '/am-toolkit/assets/js/cart.js',
            '/am-toolkit/assets/js/account-welcome.js',
            '/am-toolkit/assets/js/account-onboarding.js',
        ];

        return array_values(array_unique(array_merge($excludes, $criticalScripts)));
    }

    public function markCriticalScript(string $tag, string $handle, string $src): string
    {
        $criticalHandles = [
            'jquery-core',
            'jquery-migrate',
            'wc-add-to-cart',
            'wc-cart',
            'wc-cart-fragments',
            'am-toolkit-core',
            'am-toolkit-toast',
            'am-toolkit-woocommerce-toast',
            'am-toolkit-cart',
            'am-toolkit-account-welcome',
            'am-toolkit-account-onboarding',
        ];

        if (!in_array($handle, $criticalHandles, true) || str_contains($tag, 'data-no-defer')) {
            return $tag;
        }

        return str_replace('<script ', '<script data-no-defer="1" ', $tag);
    }
}
