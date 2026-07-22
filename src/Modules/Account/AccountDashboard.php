<?php

namespace AMToolkit\Modules\Account;

use WP_User;

defined('ABSPATH') || exit;

final class AccountDashboard
{
    /** @var array<int, int> */
    private array $purchasedProductIds = [];

    private bool $purchasedProductIdsLoaded = false;

    public function boot(): void
    {
        add_shortcode('am_account_greeting', [$this, 'renderGreeting']);
        add_shortcode('am_account_profile', [$this, 'renderProfile']);
        add_shortcode('am_account_recent_products', [$this, 'renderRecentProducts']);
        add_shortcode('am_account_last_order', [$this, 'renderLastOrder']);
        add_shortcode('am_account_attention', [$this, 'renderAttention']);
        add_shortcode('am_account_shortcut', [$this, 'renderShortcut']);
        add_shortcode('am_account_products_summary', [$this, 'renderProductsSummary']);
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

        foreach (ManualProductAssignments::assignments($user->ID) as $productId => $assignedAt) {
            $product = wc_get_product($productId);

            if (!$product) {
                continue;
            }

            $products[$productId] = [
                'name' => $product->get_name(),
                'url'  => $product->is_visible() ? $product->get_permalink() : '',
            ];

            if (count($products) >= $limit) {
                break;
            }
        }

        foreach ($orders as $order) {
            if (count($products) >= $limit) {
                break;
            }

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

    /**
     * Renders one dashboard shortcut.
     *
     * Supported types: products, orders, details and consultations.
     *
     * @param array<string, mixed> $attributes Shortcode attributes.
     */
    public function renderShortcut(array $attributes = []): string
    {
        $user = $this->currentUser();

        if (!$user || !function_exists('wc_get_page_permalink')) {
            return '';
        }

        $attributes = shortcode_atts(
            ['type' => 'products'],
            $attributes,
            'am_account_shortcut'
        );

        $type = sanitize_key((string) $attributes['type']);
        $accountUrl = wc_get_page_permalink('myaccount');
        $configuration = $this->shortcutConfiguration($type, $user, $accountUrl);

        if ($configuration === null) {
            return '';
        }

        $tag = $configuration['url'] !== '' ? 'a' : 'div';
        $className = 'am-account-shortcut-content am-account-shortcut-content--' . $type;

        if ($configuration['url'] === '') {
            $className .= ' is-disabled';
        }

        ob_start();
        ?>
        <<?php echo esc_attr($tag); ?>
            class="<?php echo esc_attr($className); ?>"
            <?php if ($configuration['url'] !== '') : ?>
                href="<?php echo esc_url($configuration['url']); ?>"
            <?php else : ?>
                aria-disabled="true"
            <?php endif; ?>
        >
            <span class="am-account-shortcut-content__icon" aria-hidden="true">
                <?php echo $this->shortcutIcon($type); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            </span>

            <strong class="am-account-shortcut-content__title">
                <?php echo esc_html($configuration['title']); ?>
            </strong>

            <span class="am-account-shortcut-content__description">
                <?php echo esc_html($configuration['description']); ?>
            </span>

            <?php if ($configuration['url'] !== '') : ?>
                <span class="am-account-shortcut-content__arrow" aria-hidden="true">
                    <svg viewBox="0 0 24 24" focusable="false">
                        <path d="M5 12h14m-5-5 5 5-5 5" />
                    </svg>
                </span>
            <?php endif; ?>
        </<?php echo esc_attr($tag); ?>>
        <?php

        return (string) ob_get_clean();
    }

    /**
     * @return array{title: string, description: string, url: string}|null
     */
    private function shortcutConfiguration(string $type, WP_User $user, string $accountUrl): ?array
    {
        if ($type === 'products') {
            return [
                'title'       => __('Moje produkty', 'am-toolkit'),
                'description' => sprintf(
                    __('Zakupione produkty: %d', 'am-toolkit'),
                    $this->purchasedProductCount($user->ID)
                ),
                'url'         => wc_get_endpoint_url('moje-produkty', '', $accountUrl),
            ];
        }

        if ($type === 'orders') {
            $orderCount = function_exists('wc_get_customer_order_count')
                ? wc_get_customer_order_count($user->ID)
                : 0;

            return [
                'title'       => __('Zamówienia', 'am-toolkit'),
                'description' => sprintf(
                    __('Liczba zamówień: %d', 'am-toolkit'),
                    $orderCount
                ),
                'url'         => wc_get_endpoint_url('orders', '', $accountUrl),
            ];
        }

        if ($type === 'details') {
            $isComplete = trim((string) $user->first_name) !== '' &&
                trim((string) $user->last_name) !== '';

            return [
                'title'       => __('Dane konta', 'am-toolkit'),
                'description' => $isComplete
                    ? __('Dane są uzupełnione', 'am-toolkit')
                    : __('Uzupełnij swoje dane', 'am-toolkit'),
                'url'         => wc_get_endpoint_url('edit-account', '', $accountUrl),
            ];
        }

        if ($type === 'consultations') {
            return [
                'title'       => __('Konsultacje', 'am-toolkit'),
                'description' => __('Funkcja pojawi się wkrótce', 'am-toolkit'),
                'url'         => '',
            ];
        }

        return null;
    }

    private function purchasedProductCount(int $userId): int
    {
        return count($this->purchasedProductIds($userId));
    }

    /**
     * Renders purchased-product counts for the three dashboard categories.
     *
     * @param array<string, mixed> $attributes Shortcode attributes.
     */
    public function renderProductsSummary(array $attributes = []): string
    {
        $user = $this->currentUser();

        if (!$user || !taxonomy_exists('product_cat')) {
            return '';
        }

        $attributes = shortcode_atts(
            [
                'consultations' => 'consultacje-i-mentoring',
                'courses'       => 'kursy-online',
                'downloads'     => 'workbooki-e-booki-szablony',
            ],
            $attributes,
            'am_account_products_summary'
        );

        $productIds = $this->purchasedProductIds($user->ID);
        $categories = [
            [
                'label' => __('Konsultacje', 'am-toolkit'),
                'slug'  => sanitize_title((string) $attributes['consultations']),
            ],
            [
                'label' => __('Kursy', 'am-toolkit'),
                'slug'  => sanitize_title((string) $attributes['courses']),
            ],
            [
                'label' => __('Pliki do pobrania', 'am-toolkit'),
                'slug'  => sanitize_title((string) $attributes['downloads']),
            ],
        ];

        foreach ($categories as &$category) {
            $category['count'] = 0;

            foreach ($productIds as $productId) {
                if (has_term($category['slug'], 'product_cat', $productId)) {
                    $category['count']++;
                }
            }
        }
        unset($category);

        ob_start();
        ?>
        <a
            class="am-account-products-summary__link"
            href="<?php echo esc_url(wc_get_account_endpoint_url('moje-produkty')); ?>"
            aria-label="<?php echo esc_attr__('Przejdź do wszystkich kupionych produktów', 'am-toolkit'); ?>"
        >
            <ul class="am-account-products-summary" aria-label="<?php echo esc_attr__('Podsumowanie zakupionych produktów', 'am-toolkit'); ?>">
                <?php foreach ($categories as $category) : ?>
                    <li class="am-account-products-summary__item">
                        <span class="am-account-products-summary__icon" aria-hidden="true">✓</span>
                        <span class="am-account-products-summary__label">
                            <?php echo esc_html($category['label']); ?>
                        </span>
                        <span class="am-account-products-summary__count" aria-label="<?php echo esc_attr(sprintf(__('Liczba: %d', 'am-toolkit'), $category['count'])); ?>">
                            <?php echo esc_html((string) $category['count']); ?>
                        </span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </a>
        <?php

        return (string) ob_get_clean();
    }

    /**
     * @return array<int, int>
     */
    private function purchasedProductIds(int $userId): array
    {
        if ($this->purchasedProductIdsLoaded) {
            return $this->purchasedProductIds;
        }

        $this->purchasedProductIdsLoaded = true;

        if (!function_exists('wc_get_orders')) {
            return [];
        }

        $orderIds = wc_get_orders([
            'customer_id' => $userId,
            'status'      => ['wc-processing', 'wc-completed'],
            'limit'       => -1,
            'return'      => 'ids',
        ]);
        foreach ($orderIds as $orderId) {
            $order = wc_get_order($orderId);

            if (!$order) {
                continue;
            }

            foreach ($order->get_items('line_item') as $item) {
                $productId = $item->get_variation_id() ?: $item->get_product_id();

                if ($productId) {
                    $parentProductId = $item->get_product_id();
                    $this->purchasedProductIds[$parentProductId ?: $productId] = $parentProductId ?: $productId;
                }
            }
        }

        foreach (array_keys(ManualProductAssignments::assignments($userId)) as $productId) {
            $this->purchasedProductIds[$productId] = $productId;
        }

        return array_values($this->purchasedProductIds);
    }

    private function shortcutIcon(string $type): string
    {
        $icons = [
            'products' => '<svg viewBox="0 0 24 24" focusable="false"><path d="M5 8h14l-1 12H6L5 8Zm3 0a4 4 0 0 1 8 0"/></svg>',
            'orders' => '<svg viewBox="0 0 24 24" focusable="false"><path d="M7 3h10v18l-2-1.5L12 21l-3-1.5L7 21V3Zm3 5h4m-4 4h4"/></svg>',
            'details' => '<svg viewBox="0 0 24 24" focusable="false"><path d="M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm7 9a7 7 0 0 0-14 0"/></svg>',
            'consultations' => '<svg viewBox="0 0 24 24" focusable="false"><path d="M5 5h14v11H9l-4 4V5Zm4 5h6"/></svg>',
        ];

        return $icons[$type] ?? '';
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
