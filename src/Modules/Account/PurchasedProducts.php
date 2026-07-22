<?php

namespace AMToolkit\Modules\Account;

defined('ABSPATH') || exit;

final class PurchasedProducts
{
    private const ENDPOINT = 'moje-produkty';
    private const REWRITE_VERSION = '1';

    public function boot(): void
    {
        add_action('init', [$this, 'registerEndpoint']);
        add_action('init', [$this, 'maybeFlushRewriteRules'], 99);
        add_filter('woocommerce_get_query_vars', [$this, 'addQueryVar']);
        add_filter('woocommerce_account_menu_items', [$this, 'addMenuItem']);
        add_action('woocommerce_account_' . self::ENDPOINT . '_endpoint', [$this, 'output']);
        add_shortcode('am_account_purchased_products', [$this, 'render']);
    }

    public function registerEndpoint(): void
    {
        add_rewrite_endpoint(self::ENDPOINT, EP_ROOT | EP_PAGES);
    }

    /** @param array<string, string> $queryVars */
    public function addQueryVar(array $queryVars): array
    {
        $queryVars[self::ENDPOINT] = self::ENDPOINT;
        return $queryVars;
    }

    /** @param array<string, string> $items */
    public function addMenuItem(array $items): array
    {
        $result = [];

        foreach ($items as $key => $label) {
            $result[$key] = $label;

            if ($key === 'dashboard') {
                $result[self::ENDPOINT] = __('Moje produkty', 'am-toolkit');
            }
        }

        if (!isset($result[self::ENDPOINT])) {
            $result[self::ENDPOINT] = __('Moje produkty', 'am-toolkit');
        }

        return $result;
    }

    public function output(): void
    {
        echo $this->render(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }

    public function render(): string
    {
        if (!is_user_logged_in() || !function_exists('wc_get_orders')) {
            return '';
        }

        $groups = [
            'consultations' => [
                'label' => __('Konsultacje', 'am-toolkit'),
                'slug'  => 'consultacje-i-mentoring',
                'items' => [],
            ],
            'courses' => [
                'label' => __('Kursy', 'am-toolkit'),
                'slug'  => 'kursy-online',
                'items' => [],
            ],
            'downloads' => [
                'label' => __('Pliki do pobrania', 'am-toolkit'),
                'slug'  => 'workbooki-e-booki-szablony',
                'items' => [],
            ],
            'other' => [
                'label' => __('Pozostałe produkty', 'am-toolkit'),
                'slug'  => '',
                'items' => [],
            ],
        ];

        foreach ($this->purchasedProducts(get_current_user_id()) as $product) {
            $groupKey = 'other';

            foreach (['consultations', 'courses', 'downloads'] as $candidate) {
                if (has_term($groups[$candidate]['slug'], 'product_cat', $product['id'])) {
                    $groupKey = $candidate;
                    break;
                }
            }

            $groups[$groupKey]['items'][] = $product;
        }

        if ($groups['other']['items'] === []) {
            unset($groups['other']);
        }

        $total = array_sum(array_map(
            static fn(array $group): int => count($group['items']),
            $groups
        ));

        ob_start();
        ?>
        <section class="am-purchased-products" aria-labelledby="am-purchased-products-title">
            <header class="am-purchased-products__header">
                <div>
                    <span class="am-purchased-products__eyebrow"><?php echo esc_html__('Twoja biblioteka', 'am-toolkit'); ?></span>
                    <h2 id="am-purchased-products-title" class="am-purchased-products__title"><?php echo esc_html__('Moje produkty', 'am-toolkit'); ?></h2>
                    <p class="am-purchased-products__intro"><?php echo esc_html__('Wszystkie kupione kursy, konsultacje i materiały znajdziesz w jednym miejscu.', 'am-toolkit'); ?></p>
                </div>
                <span class="am-purchased-products__total">
                    <?php echo esc_html(sprintf(_n('%d produkt', '%d produktów', $total, 'am-toolkit'), $total)); ?>
                </span>
            </header>

            <?php foreach ($groups as $key => $group) : ?>
                <section class="am-purchased-products__group" aria-labelledby="am-products-<?php echo esc_attr($key); ?>">
                    <header class="am-purchased-products__group-header">
                        <h3 id="am-products-<?php echo esc_attr($key); ?>"><?php echo esc_html($group['label']); ?></h3>
                        <span><?php echo esc_html((string) count($group['items'])); ?></span>
                    </header>

                    <?php if ($group['items'] === []) : ?>
                        <div class="am-purchased-products__empty">
                            <span aria-hidden="true">✓</span>
                            <p><?php echo esc_html__('Nie masz jeszcze produktów w tej kategorii.', 'am-toolkit'); ?></p>
                        </div>
                    <?php else : ?>
                        <div class="am-purchased-products__grid">
                            <?php foreach ($group['items'] as $product) : ?>
                                <article class="am-purchased-product">
                                    <div class="am-purchased-product__image">
                                        <?php echo $product['image']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                                    </div>
                                    <div class="am-purchased-product__content">
                                        <span class="am-purchased-product__date">
                                            <?php echo esc_html(sprintf(__('Kupiono: %s', 'am-toolkit'), $product['date'])); ?>
                                        </span>
                                        <h4><?php echo esc_html($product['name']); ?></h4>

                                        <?php if ($product['url'] !== '') : ?>
                                            <a class="am-purchased-product__action" href="<?php echo esc_url($product['url']); ?>">
                                                <?php echo esc_html__('Zobacz produkt', 'am-toolkit'); ?>
                                                <span aria-hidden="true">→</span>
                                            </a>
                                        <?php else : ?>
                                            <span class="am-purchased-product__unavailable"><?php echo esc_html__('Dostęp w przygotowaniu', 'am-toolkit'); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>
            <?php endforeach; ?>
        </section>
        <?php

        return (string) ob_get_clean();
    }

    public function maybeFlushRewriteRules(): void
    {
        if (get_option('amt_purchased_products_rewrite_version') === self::REWRITE_VERSION) {
            return;
        }

        flush_rewrite_rules(false);
        update_option('amt_purchased_products_rewrite_version', self::REWRITE_VERSION, false);
    }

    /** @return array<int, array{id: int, name: string, url: string, image: string, date: string}> */
    private function purchasedProducts(int $userId): array
    {
        $orders = wc_get_orders([
            'customer_id' => $userId,
            'status'      => ['wc-processing', 'wc-completed'],
            'limit'       => -1,
            'orderby'     => 'date',
            'order'       => 'DESC',
            'return'      => 'objects',
        ]);
        $products = [];

        foreach ($orders as $order) {
            $createdAt = $order->get_date_created();
            $date = $createdAt ? wp_date(get_option('date_format'), $createdAt->getTimestamp()) : '';

            foreach ($order->get_items('line_item') as $item) {
                $productId = (int) $item->get_product_id();

                if (!$productId || isset($products[$productId])) {
                    continue;
                }

                $product = wc_get_product($productId);
                $products[$productId] = [
                    'id'    => $productId,
                    'name'  => $product ? $product->get_name() : $item->get_name(),
                    'url'   => $product && $product->is_visible() ? $product->get_permalink() : '',
                    'image' => $product
                        ? $product->get_image('woocommerce_thumbnail', ['loading' => 'lazy'])
                        : wc_placeholder_img('woocommerce_thumbnail'),
                    'date'  => $date,
                ];
            }
        }

        return array_values($products);
    }
}
