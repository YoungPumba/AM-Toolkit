<?php

namespace AMToolkit\Modules\Account;

defined('ABSPATH') || exit;

final class WelcomeAnimation
{
    private bool $rendered = false;

    public function boot(): void
    {
        add_action('wp_enqueue_scripts', [$this, 'enqueue']);
        add_action('wp_body_open', [$this, 'render']);
        add_action('wp_footer', [$this, 'render'], 1);
    }

    public function enqueue(): void
    {
        if (!$this->shouldRun()) {
            return;
        }

        wp_enqueue_style(
            'am-toolkit-account-welcome',
            AM_TOOLKIT_URL . 'assets/css/account-welcome.css',
            ['am-toolkit-core'],
            $this->assetVersion('assets/css/account-welcome.css')
        );

        wp_enqueue_script(
            'am-toolkit-account-welcome',
            AM_TOOLKIT_URL . 'assets/js/account-welcome.js',
            [],
            $this->assetVersion('assets/js/account-welcome.js'),
            true
        );

        wp_add_inline_script(
            'am-toolkit-account-welcome',
            'window.AMTAccountWelcome = ' . wp_json_encode([
                'animationUrl' => AM_TOOLKIT_URL . 'assets/animations/welcome.json',
                'storageKey'   => 'amt_account_welcome_' . get_current_user_id(),
                'preview'      => $this->isPreview(),
            ]) . ';',
            'before'
        );
    }

    public function render(): void
    {
        if ($this->rendered || !$this->shouldRun()) {
            return;
        }

        $this->rendered = true;
        ?>
        <div class="am-account-welcome" data-am-account-welcome hidden aria-hidden="true">
            <div class="am-account-welcome__shade am-account-welcome__shade--top"></div>
            <div class="am-account-welcome__shade am-account-welcome__shade--bottom"></div>
            <div class="am-account-welcome__band"></div>

            <div class="am-account-welcome__animation" data-am-account-welcome-animation>
                <span class="am-account-welcome__fallback">Welcome</span>
            </div>
        </div>
        <?php
    }

    private function shouldRun(): bool
    {
        if (
            !is_user_logged_in() ||
            !function_exists('is_account_page') ||
            !is_account_page()
        ) {
            return false;
        }

        if (function_exists('is_wc_endpoint_url') && is_wc_endpoint_url()) {
            return false;
        }

        if (isset($_GET['elementor-preview'])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            return false;
        }

        if (
            isset($_GET['amt-onboarding']) || // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            isset($_GET['password-reset']) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        ) {
            return false;
        }

        return true;
    }

    private function isPreview(): bool
    {
        return current_user_can('manage_options') &&
            isset($_GET['amt-welcome-preview']) && // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            sanitize_text_field(wp_unslash($_GET['amt-welcome-preview'])) === '1'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    }

    private function assetVersion(string $relativePath): string
    {
        $absolutePath = AM_TOOLKIT_PATH . $relativePath;

        return file_exists($absolutePath)
            ? (string) filemtime($absolutePath)
            : AM_TOOLKIT_VERSION;
    }
}
