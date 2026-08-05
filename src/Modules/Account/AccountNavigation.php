<?php

namespace AMToolkit\Modules\Account;

defined('ABSPATH') || exit;

final class AccountNavigation
{
    public function boot(): void
    {
        add_shortcode('am_account_menu', [$this, 'render']);
    }

    public function render(): string
    {
        if (
            !function_exists('wc_get_page_permalink') ||
            !function_exists('wc_get_account_endpoint_url')
        ) {
            return '';
        }

        $accountUrl = wc_get_page_permalink('myaccount');

        if (!$accountUrl) {
            return '';
        }

        if (!is_user_logged_in()) {
            return $this->renderGuestLink($accountUrl);
        }

        return $this->renderCustomerMenu($accountUrl);
    }

    private function renderGuestLink(string $accountUrl): string
    {
        $loginUrl = trailingslashit($accountUrl) . '#logowanie';

        return sprintf(
            '<a class="am-account-menu__trigger am-account-menu__trigger--guest" href="%1$s" aria-label="%2$s">%3$s<span class="screen-reader-text">%2$s</span></a>',
            esc_url($loginUrl),
            esc_attr__('Zaloguj się lub załóż konto', 'am-toolkit'),
            $this->userIcon()
        );
    }

    private function renderCustomerMenu(string $accountUrl): string
    {
        $user = wp_get_current_user();

        if (!$user->exists()) {
            return '';
        }

        $panelId    = wp_unique_id('am-account-menu-panel-');
        $displayName = trim((string) $user->display_name);

        if ($displayName === '') {
            $displayName = (string) $user->user_login;
        }

        $helpUrl = trailingslashit($accountUrl) . '#pomoc-konto';
        $logoutUrl = function_exists('wc_logout_url')
            ? wc_logout_url($accountUrl)
            : wp_logout_url($accountUrl);

        ob_start();
        ?>
        <div class="am-account-menu" data-am-account-menu>
            <button
                class="am-account-menu__trigger"
                type="button"
                aria-label="<?php echo esc_attr__('Otwórz menu mojego konta', 'am-toolkit'); ?>"
                aria-expanded="false"
                aria-controls="<?php echo esc_attr($panelId); ?>"
                data-am-account-menu-trigger
            >
                <?php echo $this->userIcon(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                <span class="screen-reader-text">
                    <?php echo esc_html__('Moje konto', 'am-toolkit'); ?>
                </span>
            </button>

            <div
                id="<?php echo esc_attr($panelId); ?>"
                class="am-account-menu__panel"
                aria-hidden="true"
                data-am-account-menu-panel
            >
                <header class="am-account-menu__profile">
                    <span class="am-account-menu__avatar">
                        <?php
                        echo get_avatar(
                            $user->ID,
                            52,
                            '',
                            $displayName,
                            ['class' => 'am-account-menu__avatar-image']
                        ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                        ?>
                    </span>
                    <span class="am-account-menu__profile-copy">
                        <strong><?php echo esc_html($displayName); ?></strong>
                        <span><?php echo esc_html($user->user_email); ?></span>
                    </span>
                </header>

                <nav class="am-account-menu__navigation" aria-label="<?php echo esc_attr__('Nawigacja mojego konta', 'am-toolkit'); ?>">
                    <ul class="am-account-menu__list">
                        <?php foreach ($this->menuItems($accountUrl) as $item) : ?>
                            <li>
                                <a
                                    class="am-account-menu__link"
                                    href="<?php echo esc_url($item['url']); ?>"
                                    <?php echo $item['current'] ? 'aria-current="page"' : ''; ?>
                                >
                                    <span class="am-account-menu__link-icon" aria-hidden="true">
                                        <?php echo $this->menuIcon($item['icon']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                                    </span>
                                    <span><?php echo esc_html($item['label']); ?></span>
                                    <span class="am-account-menu__arrow" aria-hidden="true">→</span>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>

                    <a class="am-account-menu__help" href="<?php echo esc_url($helpUrl); ?>">
                        <span class="am-account-menu__link-icon" aria-hidden="true">
                            <?php echo $this->menuIcon('help'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                        </span>
                        <span>
                            <strong><?php echo esc_html__('Kontakt i pomoc', 'am-toolkit'); ?></strong>
                            <small><?php echo esc_html__('Napisz, jeśli potrzebujesz wsparcia', 'am-toolkit'); ?></small>
                        </span>
                        <span class="am-account-menu__arrow" aria-hidden="true">→</span>
                    </a>
                </nav>

                <footer class="am-account-menu__footer">
                    <a class="am-account-menu__logout" href="<?php echo esc_url($logoutUrl); ?>">
                        <?php echo esc_html__('Wyloguj się', 'am-toolkit'); ?>
                    </a>
                </footer>
            </div>
        </div>
        <?php

        return (string) ob_get_clean();
    }

    /**
     * @return array<int, array{label: string, url: string, icon: string, current: bool}>
     */
    private function menuItems(string $accountUrl): array
    {
        return [
            [
                'label'   => __('Panel główny', 'am-toolkit'),
                'url'     => $accountUrl,
                'icon'    => 'dashboard',
                'current' => $this->isCurrentEndpoint('dashboard'),
            ],
            [
                'label'   => __('Moje produkty', 'am-toolkit'),
                'url'     => wc_get_account_endpoint_url('moje-produkty'),
                'icon'    => 'products',
                'current' => $this->isCurrentEndpoint('moje-produkty'),
            ],
            [
                'label'   => __('Zamówienia', 'am-toolkit'),
                'url'     => wc_get_account_endpoint_url('orders'),
                'icon'    => 'orders',
                'current' => $this->isCurrentEndpoint('orders') || $this->isCurrentEndpoint('view-order'),
            ],
            [
                'label'   => __('Dane konta', 'am-toolkit'),
                'url'     => wc_get_account_endpoint_url('edit-account'),
                'icon'    => 'details',
                'current' => $this->isCurrentEndpoint('edit-account'),
            ],
            [
                'label'   => __('Adresy', 'am-toolkit'),
                'url'     => wc_get_account_endpoint_url('edit-address'),
                'icon'    => 'address',
                'current' => $this->isCurrentEndpoint('edit-address'),
            ],
        ];
    }

    private function isCurrentEndpoint(string $endpoint): bool
    {
        if (
            !function_exists('is_account_page') ||
            !is_account_page() ||
            !function_exists('is_wc_endpoint_url')
        ) {
            return false;
        }

        if ($endpoint === 'dashboard') {
            return !is_wc_endpoint_url();
        }

        return is_wc_endpoint_url($endpoint);
    }

    private function userIcon(): string
    {
        return '<svg class="am-account-menu__user-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><circle cx="12" cy="7.25" r="4.25"/><path d="M4.25 21a7.75 7.75 0 0 1 15.5 0Z"/></svg>';
    }

    private function menuIcon(string $icon): string
    {
        $icons = [
            'dashboard' => '<svg viewBox="0 0 24 24" focusable="false"><path d="M4 4h6v6H4zM14 4h6v6h-6zM4 14h6v6H4zM14 14h6v6h-6z"/></svg>',
            'products'  => '<svg viewBox="0 0 24 24" focusable="false"><path d="M5 8h14l-1 12H6L5 8Z"/><path d="M9 9V6a3 3 0 0 1 6 0v3"/></svg>',
            'orders'    => '<svg viewBox="0 0 24 24" focusable="false"><path d="M6 3h12v18l-3-2-3 2-3-2-3 2V3Z"/><path d="M9 8h6M9 12h6"/></svg>',
            'details'   => '<svg viewBox="0 0 24 24" focusable="false"><circle cx="12" cy="7" r="4"/><path d="M4.5 21a7.5 7.5 0 0 1 15 0"/></svg>',
            'address'   => '<svg viewBox="0 0 24 24" focusable="false"><path d="M12 21s7-5.2 7-12a7 7 0 1 0-14 0c0 6.8 7 12 7 12Z"/><circle cx="12" cy="9" r="2.5"/></svg>',
            'help'      => '<svg viewBox="0 0 24 24" focusable="false"><path d="M4 5h16v11H8l-4 4V5Z"/><path d="M9 9h6M9 12h4"/></svg>',
        ];

        return $icons[$icon] ?? $icons['dashboard'];
    }
}
