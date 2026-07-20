<?php

namespace AMToolkit\Core;

use AMToolkit\Settings\CheckoutNotice;

if (!defined('ABSPATH')) {
    exit;
}

final class Assets
{
    public function boot(): void
    {
        add_action('wp_enqueue_scripts', [$this, 'enqueue']);
    }

    public function enqueue(): void
    {
        wp_enqueue_style(
            'am-toolkit-core',
            AM_TOOLKIT_URL . 'assets/css/core.css',
            [],
            $this->assetVersion('assets/css/core.css')
        );

        wp_enqueue_style(
            'am-toolkit-toast',
            AM_TOOLKIT_URL . 'assets/css/toast.css',
            ['am-toolkit-core'],
            $this->assetVersion('assets/css/toast.css')
        );

        wp_enqueue_style(
            'am-toolkit-checkout',
            AM_TOOLKIT_URL . 'assets/css/checkout.css',
            ['am-toolkit-core'],
            $this->assetVersion('assets/css/checkout.css')
        );

        wp_add_inline_style('am-toolkit-checkout', CheckoutNotice::inlineCss());

        wp_enqueue_style(
            'am-toolkit-account',
            AM_TOOLKIT_URL . 'assets/css/account.css',
            ['am-toolkit-core'],
            $this->assetVersion('assets/css/account.css')
        );

        wp_enqueue_script(
            'am-toolkit-core',
            AM_TOOLKIT_URL . 'assets/js/core.js',
            [],
            $this->assetVersion('assets/js/core.js'),
            true
        );

        wp_enqueue_script(
            'am-toolkit-toast',
            AM_TOOLKIT_URL . 'assets/js/toast.js',
            ['am-toolkit-core'],
            $this->assetVersion('assets/js/toast.js'),
            true
        );
    }

    /**
     * Uses the file modification time during development to prevent stale assets.
     */
    private function assetVersion(string $relativePath): string
    {
        $absolutePath = AM_TOOLKIT_PATH . $relativePath;

        return file_exists($absolutePath)
            ? (string) filemtime($absolutePath)
            : AM_TOOLKIT_VERSION;
    }
}
