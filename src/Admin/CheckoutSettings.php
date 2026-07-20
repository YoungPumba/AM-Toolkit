<?php

namespace AMToolkit\Admin;

use AMToolkit\Settings\CheckoutNotice;

if (!defined('ABSPATH')) {
    exit;
}

final class CheckoutSettings
{
    private const PAGE_SLUG = 'am-toolkit-checkout';
    private const SETTINGS_GROUP = 'am_toolkit_checkout_notice';

    public function boot(): void
    {
        add_action('admin_menu', [$this, 'registerMenu']);
        add_action('admin_init', [$this, 'registerSettings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue']);
        add_action('admin_post_am_toolkit_reset_checkout_notice', [$this, 'reset']);
    }

    public function registerMenu(): void
    {
        add_submenu_page(
            'am-toolkit',
            __('AM Toolkit — Checkout', 'am-toolkit'),
            __('Checkout', 'am-toolkit'),
            'manage_options',
            self::PAGE_SLUG,
            [$this, 'render']
        );
    }

    public function registerSettings(): void
    {
        register_setting(
            self::SETTINGS_GROUP,
            CheckoutNotice::OPTION_NAME,
            [
                'type'              => 'array',
                'sanitize_callback' => [CheckoutNotice::class, 'sanitize'],
                'default'           => CheckoutNotice::defaults(),
            ]
        );
    }

    public function enqueue(string $hookSuffix): void
    {
        if ('am-toolkit_page_' . self::PAGE_SLUG !== $hookSuffix) {
            return;
        }

        $this->enqueueStyle('am-toolkit-admin-notifications', 'assets/css/admin-notifications.css');
        $this->enqueueStyle(
            'am-toolkit-admin-checkout',
            'assets/css/admin-checkout.css',
            ['am-toolkit-admin-notifications']
        );
        $this->enqueueScript('am-toolkit-admin-checkout', 'assets/js/admin-checkout.js');
    }

    public function render(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $settings = CheckoutNotice::get();
        $optionName = CheckoutNotice::OPTION_NAME;
        $resetUrl = wp_nonce_url(
            admin_url('admin-post.php?action=am_toolkit_reset_checkout_notice'),
            'am_toolkit_reset_checkout_notice'
        );
        ?>
        <div class="wrap amt-admin" data-amt-checkout-settings>
            <div class="amt-admin__header">
                <div>
                    <h1><?php esc_html_e('AM Toolkit — Checkout', 'am-toolkit'); ?></h1>
                    <p><?php esc_html_e('Dostosuj wygląd podsumowania błędów nad formularzem zamówienia.', 'am-toolkit'); ?></p>
                </div>
                <span class="amt-admin__version">v<?php echo esc_html(AM_TOOLKIT_VERSION); ?></span>
            </div>

            <?php if (isset($_GET['am-toolkit-reset'])) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
                <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Przywrócono ustawienia domyślne.', 'am-toolkit'); ?></p></div>
            <?php endif; ?>

            <?php settings_errors(); ?>

            <form action="options.php" method="post">
                <?php settings_fields(self::SETTINGS_GROUP); ?>

                <div class="amt-admin__grid">
                    <section class="amt-admin-card">
                        <h2><?php esc_html_e('Typografia', 'am-toolkit'); ?></h2>

                        <?php $this->selectField($optionName, 'font_family', __('Rodzina czcionki', 'am-toolkit'), $settings['font_family'], [
                            'Poppins' => 'Poppins',
                            'inherit' => __('Czcionka motywu', 'am-toolkit'),
                        ]); ?>

                        <?php $this->numberField($optionName, 'font_size', __('Rozmiar tekstu', 'am-toolkit'), $settings['font_size'], 11, 24, 'px'); ?>

                        <?php $this->selectField($optionName, 'font_weight', __('Grubość tekstu', 'am-toolkit'), (string) $settings['font_weight'], $this->weightOptions()); ?>

                        <?php $this->selectField($optionName, 'link_weight', __('Grubość odnośników', 'am-toolkit'), (string) $settings['link_weight'], $this->weightOptions()); ?>

                        <?php $this->colorField($optionName, 'text_color', __('Kolor tekstu', 'am-toolkit'), $settings['text_color']); ?>
                        <?php $this->colorField($optionName, 'link_color', __('Kolor odnośników', 'am-toolkit'), $settings['link_color']); ?>
                    </section>

                    <section class="amt-admin-card">
                        <h2><?php esc_html_e('Kafelek komunikatu', 'am-toolkit'); ?></h2>

                        <?php $this->colorField($optionName, 'background', __('Kolor tła', 'am-toolkit'), $settings['background']); ?>
                        <?php $this->colorField($optionName, 'border_color', __('Kolor ramki', 'am-toolkit'), $settings['border_color']); ?>
                        <?php $this->colorField($optionName, 'icon_color', __('Kolor ikony', 'am-toolkit'), $settings['icon_color']); ?>
                        <?php $this->numberField($optionName, 'border_width', __('Grubość ramki', 'am-toolkit'), $settings['border_width'], 0, 5, 'px'); ?>
                        <?php $this->numberField($optionName, 'radius', __('Zaokrąglenie rogów', 'am-toolkit'), $settings['radius'], 0, 50, 'px'); ?>
                    </section>

                    <section class="amt-admin-card amt-admin-card--wide">
                        <h2><?php esc_html_e('Podgląd', 'am-toolkit'); ?></h2>
                        <div
                            class="amt-checkout-preview"
                            data-amt-checkout-preview
                            style="<?php echo esc_attr($this->previewStyle($settings)); ?>"
                        >
                            <span class="amt-checkout-preview__icon" aria-hidden="true">!</span>
                            <div>
                                <div><?php esc_html_e('Imię rozliczeniowe jest wymaganym polem.', 'am-toolkit'); ?></div>
                                <div><?php esc_html_e('Adres email rozliczeniowy jest wymaganym polem.', 'am-toolkit'); ?></div>
                                <a href="#" onclick="return false;"><?php esc_html_e('Przejdź do danych konta', 'am-toolkit'); ?></a>
                            </div>
                        </div>
                    </section>
                </div>

                <div class="amt-admin__actions">
                    <?php submit_button(__('Zapisz ustawienia', 'am-toolkit'), 'primary', 'submit', false); ?>
                    <a class="button button-secondary" href="<?php echo esc_url($resetUrl); ?>">
                        <?php esc_html_e('Przywróć domyślne', 'am-toolkit'); ?>
                    </a>
                </div>
            </form>
        </div>
        <?php
    }

    public function reset(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Nie masz uprawnień do wykonania tej operacji.', 'am-toolkit'));
        }

        check_admin_referer('am_toolkit_reset_checkout_notice');
        update_option(CheckoutNotice::OPTION_NAME, CheckoutNotice::defaults());

        wp_safe_redirect(
            add_query_arg(
                ['page' => self::PAGE_SLUG, 'am-toolkit-reset' => '1'],
                admin_url('admin.php')
            )
        );
        exit;
    }

    private function numberField(string $optionName, string $key, string $label, int $value, int $min, int $max, string $unit): void
    {
        ?>
        <div class="amt-field">
            <label for="amt-checkout-<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></label>
            <div class="amt-field__inline">
                <input
                    id="amt-checkout-<?php echo esc_attr($key); ?>"
                    name="<?php echo esc_attr($optionName); ?>[<?php echo esc_attr($key); ?>]"
                    type="number"
                    min="<?php echo esc_attr((string) $min); ?>"
                    max="<?php echo esc_attr((string) $max); ?>"
                    value="<?php echo esc_attr((string) $value); ?>"
                    data-amt-preview-key="<?php echo esc_attr($key); ?>"
                >
                <span><?php echo esc_html($unit); ?></span>
            </div>
        </div>
        <?php
    }

    private function selectField(string $optionName, string $key, string $label, string $value, array $options): void
    {
        ?>
        <div class="amt-field">
            <label for="amt-checkout-<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></label>
            <select
                id="amt-checkout-<?php echo esc_attr($key); ?>"
                name="<?php echo esc_attr($optionName); ?>[<?php echo esc_attr($key); ?>]"
                data-amt-preview-key="<?php echo esc_attr($key); ?>"
            >
                <?php foreach ($options as $optionValue => $optionLabel) : ?>
                    <option value="<?php echo esc_attr((string) $optionValue); ?>" <?php selected((string) $optionValue, $value); ?>>
                        <?php echo esc_html($optionLabel); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php
    }

    private function colorField(string $optionName, string $key, string $label, string $value): void
    {
        ?>
        <div class="amt-field">
            <label for="amt-checkout-<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></label>
            <div class="amt-color-field">
                <input
                    id="amt-checkout-<?php echo esc_attr($key); ?>"
                    name="<?php echo esc_attr($optionName); ?>[<?php echo esc_attr($key); ?>]"
                    type="color"
                    value="<?php echo esc_attr($value); ?>"
                    data-amt-preview-key="<?php echo esc_attr($key); ?>"
                >
                <code data-amt-color-value><?php echo esc_html($value); ?></code>
            </div>
        </div>
        <?php
    }

    private function weightOptions(): array
    {
        return [300 => '300', 400 => '400', 500 => '500', 600 => '600', 700 => '700'];
    }

    private function previewStyle(array $settings): string
    {
        $fontFamily = 'Poppins' === $settings['font_family'] ? "'Poppins', sans-serif" : 'inherit';

        return sprintf(
            '--preview-font-family:%s;--preview-font-size:%dpx;--preview-font-weight:%d;--preview-link-weight:%d;--preview-text:%s;--preview-link:%s;--preview-icon:%s;--preview-background:%s;--preview-border:%s;--preview-border-width:%dpx;--preview-radius:%dpx;',
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

    private function enqueueStyle(string $handle, string $relativePath, array $dependencies = []): void
    {
        wp_enqueue_style($handle, AM_TOOLKIT_URL . $relativePath, $dependencies, $this->assetVersion($relativePath));
    }

    private function enqueueScript(string $handle, string $relativePath, array $dependencies = []): void
    {
        wp_enqueue_script($handle, AM_TOOLKIT_URL . $relativePath, $dependencies, $this->assetVersion($relativePath), true);
    }

    private function assetVersion(string $relativePath): string
    {
        $absolutePath = AM_TOOLKIT_PATH . $relativePath;

        return file_exists($absolutePath) ? (string) filemtime($absolutePath) : AM_TOOLKIT_VERSION;
    }
}
