<?php

namespace AMToolkit\Modules\Account;

use WP_User;

defined('ABSPATH') || exit;

final class AccountDashboard
{
    public function boot(): void
    {
        add_shortcode('am_account_greeting', [$this, 'renderGreeting']);
        add_shortcode('am_account_profile', [$this, 'renderProfile']);
        add_shortcode('am_account_recent_products', [$this, 'renderRecentProducts']);
        add_shortcode('am_account_last_order', [$this, 'renderLastOrder']);
        add_shortcode('am_account_attention', [$this, 'renderAttention']);
    }

    /**
     * Renders the dashboard greeting for the currently logged-in customer.
     */
    public function renderGreeting(): string
    {
        $user = $this->currentUser();

        if (!$user) {
            return '';
        }

        $name = $this->preferredName($user);

        return sprintf(
            '<p class="am-account-greeting">%s <strong>%s</strong>.</p>',
            esc_html__('Miło Cię widzieć,', 'am-toolkit'),
            esc_html($name)
        );
    }

    /**
     * Renders the profile summary with avatar and account edit link.
     */
    public function renderProfile(): string
    {
        $user = $this->currentUser();

        if (!$user) {
            return '';
        }

        $displayName = $user->display_name ?: $this->preferredName($user);
        $editUrl = function_exists('wc_get_account_endpoint_url')
            ? wc_get_account_endpoint_url('edit-account')
            : get_edit_profile_url($user->ID);

        $avatar = get_avatar(
            $user->ID,
            112,
            '',
            sprintf(
                /* translators: %s: customer display name. */
                __('Zdjęcie profilowe użytkownika %s', 'am-toolkit'),
                $displayName
            ),
            ['class' => 'am-account-profile__avatar-image']
        );

        ob_start();
        ?>
        <div class="am-account-profile">
            <div class="am-account-profile__details">
                <strong class="am-account-profile__name">
                    <?php echo esc_html($displayName); ?>
                </strong>

                <span class="am-account-profile__login">
                    <?php echo esc_html('@' . $user->user_login); ?>
                </span>

                <a class="am-account-profile__edit" href="<?php echo esc_url($editUrl); ?>">
                    <?php echo esc_html__('Edytuj profil', 'am-toolkit'); ?>
                </a>
            </div>

            <div class="am-account-profile__avatar">
                <?php echo $avatar; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            </div>
        </div>
        <?php

        return (string) ob_get_clean();
    }

    /**
     * Renders unique products from the customer's newest paid orders.
     *
     * @param array<string, mixed> $attributes Shortcode attributes.
     */
    public function renderRecentProducts(array $attributes = []): string
    {
        $user = $this->currentUser();

        if (!$user || !function_exists('wc_get_orders')) {
            return '';
        }

        $attributes = shortcode_atts(
            ['limit' => 3],
            $attributes,
            'am_account_recent_products'
        );

        $limit = max(1, min(12, absint($attributes['limit'])));
        $orders = wc_get_orders([
            'customer_id' => $user->ID,
            'status'      => ['wc-processing', 'wc-completed'],
            'limit'       => 20,
            'orderby'     => 'date',
            'order'       => 'DESC',
            'return'      => 'objects',
        ]);

        $products = [];

        foreach ($orders as $order) {
            foreach ($order->get_items('line_item') as $item) {
                $product = $item->get_product();
                $productId = $product ? $product->get_id() : $item->get_product_id();

                if (!$productId || isset($products[$productId])) {
                    continue;
                }

                $products[$productId] = [
                    'name' => $item->get_name(),
                    'url'  => $product && $product->is_visible()
                        ? $product->get_permalink()
                        : '',
                ];

                if (count($products) >= $limit) {
                    break 2;
                }
            }
        }

        if ($products === []) {
            return sprintf(
                '<p class="am-account-recent-products__empty">%s</p>',
                esc_html__('Nie masz jeszcze zakupionych produktów.', 'am-toolkit')
            );
        }

        ob_start();
        ?>
        <ul class="am-account-recent-products" aria-label="<?php echo esc_attr__('Ostatnio kupione produkty', 'am-toolkit'); ?>">
            <?php foreach ($products as $product) : ?>
                <li class="am-account-recent-products__item">
                    <?php if ($product['url'] !== '') : ?>
                        <a class="am-account-recent-products__link" href="<?php echo esc_url($product['url']); ?>">
                            <?php echo esc_html($product['name']); ?>
                        </a>
                    <?php else : ?>
                        <span class="am-account-recent-products__name">
                            <?php echo esc_html($product['name']); ?>
                        </span>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
        <?php

        return (string) ob_get_clean();
    }

    /**
     * Renders the customer's newest WooCommerce order.
     */
    public function renderLastOrder(): string
    {
        $user = $this->currentUser();

        if (!$user || !function_exists('wc_get_orders')) {
            return '';
        }

        $orders = wc_get_orders([
            'customer_id' => $user->ID,
            'status'      => array_keys(wc_get_order_statuses()),
            'limit'       => 1,
            'orderby'     => 'date',
            'order'       => 'DESC',
            'return'      => 'objects',
        ]);

        if ($orders === []) {
            return sprintf(
                '<p class="am-account-last-order__empty">%s</p>',
                esc_html__('Nie masz jeszcze żadnych zamówień.', 'am-toolkit')
            );
        }

        $order = $orders[0];
        $createdAt = $order->get_date_created();
        $accountUrl = wc_get_page_permalink('myaccount');
        $detailsUrl = wc_get_endpoint_url('view-order', (string) $order->get_id(), $accountUrl);
        $ordersUrl = wc_get_endpoint_url('orders', '', $accountUrl);

        ob_start();
        ?>
        <div class="am-account-last-order">
            <dl class="am-account-last-order__details">
                <div class="am-account-last-order__row">
                    <dt><?php echo esc_html__('Zamówienie', 'am-toolkit'); ?></dt>
                    <dd>#<?php echo esc_html($order->get_order_number()); ?></dd>
                </div>

                <?php if ($createdAt) : ?>
                    <div class="am-account-last-order__row">
                        <dt><?php echo esc_html__('Data', 'am-toolkit'); ?></dt>
                        <dd><?php echo esc_html(wc_format_datetime($createdAt)); ?></dd>
                    </div>
                <?php endif; ?>

                <div class="am-account-last-order__row">
                    <dt><?php echo esc_html__('Status', 'am-toolkit'); ?></dt>
                    <dd><?php echo esc_html(wc_get_order_status_name($order->get_status())); ?></dd>
                </div>

                <div class="am-account-last-order__row">
                    <dt><?php echo esc_html__('Wartość', 'am-toolkit'); ?></dt>
                    <dd><?php echo wp_kses_post($order->get_formatted_order_total()); ?></dd>
                </div>
            </dl>

            <div class="am-account-last-order__actions">
                <a class="am-account-last-order__button" href="<?php echo esc_url($detailsUrl); ?>">
                    <?php echo esc_html__('Zobacz szczegóły', 'am-toolkit'); ?>
                </a>

                <a class="am-account-last-order__all" href="<?php echo esc_url($ordersUrl); ?>">
                    <?php echo esc_html__('Wszystkie zamówienia', 'am-toolkit'); ?>
                </a>
            </div>
        </div>
        <?php

        return (string) ob_get_clean();
    }

    /**
     * Renders actionable account tasks or a reassuring all-clear state.
     */
    public function renderAttention(): string
    {
        $user = $this->currentUser();

        if (!$user || !function_exists('wc_get_page_permalink')) {
            return '';
        }

        $accountUrl = wc_get_page_permalink('myaccount');
        $tasks = [];

        if (
            trim((string) $user->first_name) === '' ||
            trim((string) $user->last_name) === ''
        ) {
            $tasks[] = [
                'label' => __('Uzupełnij imię i nazwisko', 'am-toolkit'),
                'url'   => wc_get_endpoint_url('edit-account', '', $accountUrl),
                'type'  => 'profile',
            ];
        }

        $billingFields = [
            'billing_first_name',
            'billing_last_name',
            'billing_address_1',
            'billing_city',
            'billing_postcode',
            'billing_country',
        ];

        $hasMissingBillingData = false;

        foreach ($billingFields as $field) {
            if (trim((string) get_user_meta($user->ID, $field, true)) === '') {
                $hasMissingBillingData = true;
                break;
            }
        }

        if ($hasMissingBillingData) {
            $tasks[] = [
                'label' => __('Uzupełnij dane rozliczeniowe', 'am-toolkit'),
                'url'   => wc_get_endpoint_url('edit-address', 'billing', $accountUrl),
                'type'  => 'billing',
            ];
        }

        if (function_exists('wc_get_orders')) {
            $unpaidOrders = wc_get_orders([
                'customer_id' => $user->ID,
                'status'      => ['wc-pending', 'wc-failed'],
                'limit'       => 5,
                'orderby'     => 'date',
                'order'       => 'DESC',
                'return'      => 'objects',
            ]);

            foreach ($unpaidOrders as $order) {
                if (!$order->needs_payment()) {
                    continue;
                }

                $tasks[] = [
                    'label' => sprintf(
                        /* translators: %s: WooCommerce order number. */
                        __('Dokończ płatność za zamówienie #%s', 'am-toolkit'),
                        $order->get_order_number()
                    ),
                    'url'   => $order->get_checkout_payment_url(),
                    'type'  => 'payment',
                ];

                break;
            }
        }

        if ($tasks === []) {
            return sprintf(
                '<p class="am-account-attention__empty"><span aria-hidden="true">✓</span>%s</p>',
                esc_html__('Wszystko jest w porządku — Twoje konto nie wymaga teraz żadnych działań.', 'am-toolkit')
            );
        }

        ob_start();
        ?>
        <ul class="am-account-attention" aria-label="<?php echo esc_attr__('Elementy wymagające uwagi', 'am-toolkit'); ?>">
            <?php foreach ($tasks as $task) : ?>
                <li class="am-account-attention__item am-account-attention__item--<?php echo esc_attr($task['type']); ?>">
                    <a class="am-account-attention__link" href="<?php echo esc_url($task['url']); ?>">
                        <span class="am-account-attention__icon" aria-hidden="true">!</span>
                        <span><?php echo esc_html($task['label']); ?></span>
                        <span class="am-account-attention__arrow" aria-hidden="true">→</span>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
        <?php

        return (string) ob_get_clean();
    }

    private function currentUser(): ?WP_User
    {
        if (!is_user_logged_in()) {
            return null;
        }

        $user = wp_get_current_user();

        return $user->exists() ? $user : null;
    }

    private function preferredName(WP_User $user): string
    {
        $firstName = trim((string) $user->first_name);

        if ($firstName !== '') {
            return $firstName;
        }

        $displayName = trim((string) $user->display_name);

        return $displayName !== '' ? $displayName : $user->user_login;
    }
}
