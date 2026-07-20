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
