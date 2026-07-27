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
        add_action('template_redirect', [$this, 'handleManualDownload'], 0);
        add_filter('woocommerce_get_query_vars', [$this, 'addQueryVar']);
        add_filter('woocommerce_account_menu_items', [$this, 'addMenuItem']);
        add_action('woocommerce_account_' . self::ENDPOINT . '_endpoint', [$this, 'output']);
        add_shortcode('am_account_purchased_products', [$this, 'render']);
        add_filter('template_include', [$this, 'accountTemplate'], 99);
    }

    public function accountTemplate(string $template): string
    {
        if (
            is_admin() ||
            !is_user_logged_in() ||
            !function_exists('is_wc_endpoint_url') ||
            !is_wc_endpoint_url(self::ENDPOINT)
        ) {
            return $template;
        }

        $pluginTemplate = AM_TOOLKIT_PATH . 'templates/account/purchased-products.php';

        return file_exists($pluginTemplate) ? $pluginTemplate : $template;
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

        $userId = get_current_user_id();
        $downloadsByProduct = $this->customerDownloads($userId);

        foreach ($this->purchasedProducts($userId) as $product) {
            $product['downloads'] = $downloadsByProduct[$product['id']] ?? [];

            if ($product['source'] === 'manual') {
                $product['downloads'] = $this->manualProductDownloads($product['id'], $userId);
            }

            $groupKey = 'other';

            foreach (['consultations', 'courses', 'downloads'] as $candidate) {
                if ($this->belongsToProductCategory($product['id'], $groups[$candidate]['slug'])) {
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
                    <?php echo esc_html($this->productCountLabel($total)); ?>
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
                                            <?php
                                            echo esc_html(sprintf(
                                                $product['source'] === 'manual'
                                                    ? __('Przyznano: %s', 'am-toolkit')
                                                    : __('Kupiono: %s', 'am-toolkit'),
                                                $product['date']
                                            ));
                                            ?>
                                        </span>
                                        <h4><?php echo esc_html($product['name']); ?></h4>

                                        <?php if ($product['downloads'] !== []) : ?>
                                            <div class="am-purchased-product__downloads" aria-label="<?php echo esc_attr(sprintf(__('Pliki produktu: %s', 'am-toolkit'), $product['name'])); ?>">
                                                <?php foreach ($product['downloads'] as $download) : ?>
                                                    <a class="am-purchased-product__download" href="<?php echo esc_url($download['url']); ?>">
                                                        <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                                            <path d="M12 3v12m0 0 4.5-4.5M12 15l-4.5-4.5M5 20h14" />
                                                        </svg>
                                                        <span><?php echo esc_html($download['name']); ?></span>
                                                    </a>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>

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

    /** @return array<int, array{id: int, name: string, url: string, image: string, date: string, source: string}> */
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
                    'source' => 'order',
                ];
            }
        }

        foreach (ManualProductAssignments::assignments($userId) as $productId => $assignedAt) {
            if (isset($products[$productId])) {
                continue;
            }

            $product = wc_get_product($productId);

            if (!$product) {
                continue;
            }

            $products[$productId] = [
                'id'    => $productId,
                'name'  => $product->get_name(),
                'url'   => $product->is_visible() ? $product->get_permalink() : '',
                'image' => $product->get_image('woocommerce_thumbnail', ['loading' => 'lazy']),
                'date'  => wp_date(get_option('date_format'), $assignedAt),
                'source' => 'manual',
            ];
        }

        return array_values($products);
    }

    private function productCountLabel(int $count): string
    {
        $lastDigit = $count % 10;
        $lastTwoDigits = $count % 100;

        if ($count === 1) {
            $form = __('produkt', 'am-toolkit');
        } elseif ($lastDigit >= 2 && $lastDigit <= 4 && ($lastTwoDigits < 12 || $lastTwoDigits > 14)) {
            $form = __('produkty', 'am-toolkit');
        } else {
            $form = __('produktów', 'am-toolkit');
        }

        return sprintf('%d %s', $count, $form);
    }

    /**
     * Matches both the configured product category and any of its descendants.
     *
     * A product assigned only to a child category should still appear in the
     * corresponding account group.
     */
    private function belongsToProductCategory(int $productId, string $categorySlug): bool
    {
        if ($categorySlug === '') {
            return false;
        }

        $category = get_term_by('slug', $categorySlug, 'product_cat');

        if (!$category instanceof \WP_Term) {
            return false;
        }

        $productTermIds = wp_get_post_terms($productId, 'product_cat', ['fields' => 'ids']);

        if (is_wp_error($productTermIds)) {
            return false;
        }

        $categoryId = (int) $category->term_id;

        foreach ($productTermIds as $productTermId) {
            $productTermId = (int) $productTermId;

            if (
                $productTermId === $categoryId ||
                in_array($categoryId, get_ancestors($productTermId, 'product_cat', 'taxonomy'), true)
            ) {
                return true;
            }
        }

        return false;
    }

    /** @return array<int, array<int, array{name: string, url: string}>> */
    private function customerDownloads(int $userId): array
    {
        if (!function_exists('wc_get_customer_available_downloads')) {
            return [];
        }

        $downloadsByProduct = [];

        foreach (wc_get_customer_available_downloads($userId) as $download) {
            $productId = absint($download['product_id'] ?? 0);
            $url = isset($download['download_url']) ? (string) $download['download_url'] : '';

            if (!$productId || $url === '') {
                continue;
            }

            $product = wc_get_product($productId);

            if ($product && $product->is_type('variation')) {
                $productId = $product->get_parent_id();
            }

            $file = isset($download['file']) && is_array($download['file'])
                ? $download['file']
                : [];
            $name = (string) ($file['name'] ?? $download['download_name'] ?? __('Pobierz plik', 'am-toolkit'));

            $downloadsByProduct[$productId][] = [
                'name' => sanitize_text_field($name),
                'url'  => esc_url_raw($url),
            ];
        }

        return $downloadsByProduct;
    }

    /** @return array<int, array{name: string, url: string}> */
    private function manualProductDownloads(int $productId, int $userId): array
    {
        if (!isset(ManualProductAssignments::assignments($userId)[$productId])) {
            return [];
        }

        $product = wc_get_product($productId);

        if (!$product || !$product->is_downloadable()) {
            return [];
        }

        $downloads = [];

        foreach ($product->get_downloads() as $downloadId => $download) {
            if (!$download->get_enabled() || $download->get_file() === '') {
                continue;
            }

            $downloads[] = [
                'name' => sanitize_text_field($download->get_name() ?: __('Pobierz plik', 'am-toolkit')),
                'url'  => add_query_arg([
                    'amt_manual_download' => $productId,
                    'download_id'         => $downloadId,
                    '_wpnonce'            => wp_create_nonce($this->manualDownloadNonceAction($userId, $productId, (string) $downloadId)),
                ], home_url('/')),
            ];
        }

        return $downloads;
    }

    public function handleManualDownload(): void
    {
        if (!isset($_GET['amt_manual_download'], $_GET['download_id'], $_GET['_wpnonce'])) {
            return;
        }

        $userId = get_current_user_id();
        $productId = absint(wp_unslash($_GET['amt_manual_download']));
        $downloadId = sanitize_text_field(wp_unslash($_GET['download_id']));
        $nonce = sanitize_text_field(wp_unslash($_GET['_wpnonce']));

        if (!$userId || !$productId || $downloadId === '') {
            wp_die(esc_html__('Nie masz dostępu do tego pliku.', 'am-toolkit'), '', ['response' => 403]);
        }

        $action = $this->manualDownloadNonceAction($userId, $productId, $downloadId);
        $assignments = ManualProductAssignments::assignments($userId);

        if (!wp_verify_nonce($nonce, $action) || !isset($assignments[$productId])) {
            wp_die(esc_html__('Odnośnik pobierania wygasł lub nie masz dostępu do tego pliku.', 'am-toolkit'), '', ['response' => 403]);
        }

        $product = wc_get_product($productId);
        $downloads = $product ? $product->get_downloads() : [];
        $download = $downloads[$downloadId] ?? null;

        if (!$product || !$download || !$download->get_enabled() || $download->get_file() === '') {
            wp_die(esc_html__('Ten plik nie jest już dostępny.', 'am-toolkit'), '', ['response' => 404]);
        }

        nocache_headers();
        \WC_Download_Handler::download($download->get_file(), $productId);
        exit;
    }

    private function manualDownloadNonceAction(int $userId, int $productId, string $downloadId): string
    {
        return sprintf('amt_manual_download_%d_%d_%s', $userId, $productId, $downloadId);
    }
}
