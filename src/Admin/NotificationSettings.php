<?php

namespace AMToolkit\Admin;

use AMToolkit\Settings\Notifications;

if (!defined('ABSPATH')) {
    exit;
}

final class NotificationSettings
{
    private const PAGE_SLUG = 'am-toolkit';
    private const SETTINGS_GROUP = 'am_toolkit_notifications';

    public function boot(): void
    {
        add_action('admin_menu', [$this, 'registerMenu']);
        add_action('admin_init', [$this, 'registerSettings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue']);
        add_action('admin_post_am_toolkit_reset_notifications', [$this, 'reset']);
        add_filter(
            'plugin_action_links_' . plugin_basename(AM_TOOLKIT_PATH . 'am-toolkit.php'),
            [$this, 'addPluginActionLink']
        );
    }

    public function addPluginActionLink(array $links): array
    {
        $settingsLink = sprintf(
            '<a href="%s">%s</a>',
            esc_url(admin_url('admin.php?page=' . self::PAGE_SLUG)),
            esc_html__('Ustawienia', 'am-toolkit')
        );

        array_unshift($links, $settingsLink);

        return $links;
    }

    public function registerMenu(): void
    {
        add_menu_page(
            __('AM Toolkit', 'am-toolkit'),
            __('AM Toolkit', 'am-toolkit'),
            'manage_options',
            self::PAGE_SLUG,
            [$this, 'render'],
            'dashicons-bell',
            58
        );
    }

    public function registerSettings(): void
    {
        register_setting(
            self::SETTINGS_GROUP,
            Notifications::OPTION_NAME,
            [
                'type'              => 'array',
                'sanitize_callback' => [Notifications::class, 'sanitize'],
                'default'           => Notifications::defaults(),
            ]
        );
    }

    public function enqueue(string $hookSuffix): void
    {
        if ('toplevel_page_' . self::PAGE_SLUG !== $hookSuffix) {
            return;
        }

        $this->enqueueStyle('am-toolkit-core', 'assets/css/core.css');
        $this->enqueueStyle('am-toolkit-toast', 'assets/css/toast.css', ['am-toolkit-core']);
        $this->enqueueStyle('am-toolkit-admin-notifications', 'assets/css/admin-notifications.css', ['am-toolkit-toast']);

        $this->enqueueScript('am-toolkit-core', 'assets/js/core.js');
        $this->enqueueScript('am-toolkit-toast', 'assets/js/toast.js', ['am-toolkit-core']);
        $this->enqueueScript(
            'am-toolkit-admin-notifications',
            'assets/js/admin-notifications.js',
            ['am-toolkit-toast']
        );
    }

    public function render(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $settings = Notifications::get();
        $optionName = Notifications::OPTION_NAME;
        $resetUrl = wp_nonce_url(
            admin_url('admin-post.php?action=am_toolkit_reset_notifications'),
            'am_toolkit_reset_notifications'
        );
        ?>
        <div class="wrap amt-admin">
            <div class="amt-admin__header">
                <div>
                    <h1><?php esc_html_e('AM Toolkit — Powiadomienia', 'am-toolkit'); ?></h1>
                    <p><?php esc_html_e('Edytuj komunikaty Toast Engine i sprawdź ich wygląd przed zapisaniem.', 'am-toolkit'); ?></p>
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
                        <div class="amt-admin-card__heading">
                            <div>
                                <h2><?php esc_html_e('Dodanie produktu', 'am-toolkit'); ?></h2>
                                <p><?php esc_html_e('Komunikat po pomyślnym dodaniu produktu do koszyka.', 'am-toolkit'); ?></p>
                            </div>
                            <label class="amt-switch">
                                <input
                                    id="amt-added-enabled"
                                    name="<?php echo esc_attr($optionName); ?>[added_enabled]"
                                    type="checkbox"
                                    value="1"
                                    <?php checked(1, $settings['added_enabled']); ?>
                                >
                                <span aria-hidden="true"></span>
                                <span class="screen-reader-text"><?php esc_html_e('Włącz powiadomienie dodania', 'am-toolkit'); ?></span>
                            </label>
                        </div>

                        <?php $this->textField($optionName, 'added_title', __('Nagłówek', 'am-toolkit'), $settings['added_title']); ?>
                        <?php $this->textareaField($optionName, 'added_message', __('Treść', 'am-toolkit'), $settings['added_message']); ?>

                        <p class="description">
                            <?php esc_html_e('Znacznik {product_name} zostanie zastąpiony nazwą produktu.', 'am-toolkit'); ?>
                        </p>

                        <button class="button amt-preview" type="button" data-amt-preview="added">
                            <?php esc_html_e('Pokaż podgląd', 'am-toolkit'); ?>
                        </button>
                    </section>

                    <section class="amt-admin-card">
                        <div class="amt-admin-card__heading">
                            <div>
                                <h2><?php esc_html_e('Usunięcie produktu', 'am-toolkit'); ?></h2>
                                <p><?php esc_html_e('Komunikat po usunięciu produktu z koszyka.', 'am-toolkit'); ?></p>
                            </div>
                            <label class="amt-switch">
                                <input
                                    id="amt-removed-enabled"
                                    name="<?php echo esc_attr($optionName); ?>[removed_enabled]"
                                    type="checkbox"
                                    value="1"
                                    <?php checked(1, $settings['removed_enabled']); ?>
                                >
                                <span aria-hidden="true"></span>
                                <span class="screen-reader-text"><?php esc_html_e('Włącz powiadomienie usunięcia', 'am-toolkit'); ?></span>
                            </label>
                        </div>

                        <?php $this->textField($optionName, 'removed_title', __('Nagłówek', 'am-toolkit'), $settings['removed_title']); ?>
                        <?php $this->textareaField($optionName, 'removed_message', __('Treść', 'am-toolkit'), $settings['removed_message']); ?>

                        <p class="description">
                            <?php esc_html_e('Znacznik {product_name} zostanie zastąpiony nazwą produktu.', 'am-toolkit'); ?>
                        </p>

                        <button class="button amt-preview" type="button" data-amt-preview="removed">
                            <?php esc_html_e('Pokaż podgląd', 'am-toolkit'); ?>
                        </button>
                    </section>

                    <section class="amt-admin-card amt-admin-card--wide">
                        <h2><?php esc_html_e('Ustawienia wspólne', 'am-toolkit'); ?></h2>

                        <?php $this->textField($optionName, 'cart_action', __('Tekst przycisku koszyka', 'am-toolkit'), $settings['cart_action']); ?>

                        <div class="amt-field">
                            <label for="amt-duration"><?php esc_html_e('Czas wyświetlania', 'am-toolkit'); ?></label>
                            <div class="amt-field__inline">
                                <input
                                    id="amt-duration"
                                    name="<?php echo esc_attr($optionName); ?>[duration]"
                                    type="number"
                                    min="1000"
                                    max="15000"
                                    step="250"
                                    value="<?php echo esc_attr((string) $settings['duration']); ?>"
                                >
                                <span>ms</span>
                            </div>
                            <p class="description"><?php esc_html_e('Dozwolony zakres: od 1000 do 15000 ms.', 'am-toolkit'); ?></p>
                        </div>
                    </section>
                </div>

                <div class="amt-admin__actions">
                    <?php submit_button(__('Zapisz ustawienia', 'am-toolkit'), 'primary', 'submit', false); ?>
                    <a
                        class="button button-secondary"
                        href="<?php echo esc_url($resetUrl); ?>"
                        data-amt-reset
                    ><?php esc_html_e('Przywróć domyślne', 'am-toolkit'); ?></a>
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

        check_admin_referer('am_toolkit_reset_notifications');
        update_option(Notifications::OPTION_NAME, Notifications::defaults());

        wp_safe_redirect(
            add_query_arg(
                [
                    'page'             => self::PAGE_SLUG,
                    'am-toolkit-reset' => '1',
                ],
                admin_url('admin.php')
            )
        );
        exit;
    }

    private function textField(string $optionName, string $key, string $label, string $value): void
    {
        ?>
        <div class="amt-field">
            <label for="amt-<?php echo esc_attr(str_replace('_', '-', $key)); ?>"><?php echo esc_html($label); ?></label>
            <input
                id="amt-<?php echo esc_attr(str_replace('_', '-', $key)); ?>"
                class="regular-text"
                name="<?php echo esc_attr($optionName); ?>[<?php echo esc_attr($key); ?>]"
                type="text"
                value="<?php echo esc_attr($value); ?>"
            >
        </div>
        <?php
    }

    private function textareaField(string $optionName, string $key, string $label, string $value): void
    {
        ?>
        <div class="amt-field">
            <label for="amt-<?php echo esc_attr(str_replace('_', '-', $key)); ?>"><?php echo esc_html($label); ?></label>
            <textarea
                id="amt-<?php echo esc_attr(str_replace('_', '-', $key)); ?>"
                name="<?php echo esc_attr($optionName); ?>[<?php echo esc_attr($key); ?>]"
                rows="3"
            ><?php echo esc_textarea($value); ?></textarea>
        </div>
        <?php
    }

    private function enqueueStyle(string $handle, string $relativePath, array $dependencies = []): void
    {
        wp_enqueue_style(
            $handle,
            AM_TOOLKIT_URL . $relativePath,
            $dependencies,
            $this->assetVersion($relativePath)
        );
    }

    private function enqueueScript(string $handle, string $relativePath, array $dependencies = []): void
    {
        wp_enqueue_script(
            $handle,
            AM_TOOLKIT_URL . $relativePath,
            $dependencies,
            $this->assetVersion($relativePath),
            true
        );
    }

    private function assetVersion(string $relativePath): string
    {
        $absolutePath = AM_TOOLKIT_PATH . $relativePath;

        return file_exists($absolutePath)
            ? (string) filemtime($absolutePath)
            : AM_TOOLKIT_VERSION;
    }
}
