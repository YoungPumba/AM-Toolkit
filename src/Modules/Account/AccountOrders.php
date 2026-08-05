<?php

namespace AMToolkit\Modules\Account;

defined('ABSPATH') || exit;

final class AccountOrders
{
    private const ENDPOINT = 'orders';
    private const PER_PAGE = 8;

    /** @var array<int, array<int, array{name: string, url: string}>>|null */
    private ?array $downloadsByOrder = null;

    public function boot(): void
    {
        add_shortcode('am_account_orders', [$this, 'render']);
        add_filter('template_include', [$this, 'accountTemplate'], 100);
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

        $pluginTemplate = AM_TOOLKIT_PATH . 'templates/account/orders.php';

        return file_exists($pluginTemplate) ? $pluginTemplate : $template;
    }

    public function render(): string
    {
        if (!is_user_logged_in() || !function_exists('wc_get_orders')) {
            return '';
        }

        $currentPage = $this->currentPage();
        $status      = $this->selectedStatus();
        $sort        = $this->selectedSort();
        $query = [
            'customer' => get_current_user_id(),
            'return'   => 'objects',
        ];

        if ($status !== '') {
            $query['status'] = $status;
        }

        if (in_array($sort, ['amount_desc', 'amount_asc'], true)) {
            $query['limit']   = -1;
            $query['orderby'] = 'date';
            $query['order']   = 'DESC';
            $allOrders        = wc_get_orders($query);
            $allOrders        = is_array($allOrders) ? $allOrders : [];

            usort($allOrders, static function ($first, $second) use ($sort): int {
                $firstTotal  = $first instanceof \WC_Order ? (float) $first->get_total() : 0.0;
                $secondTotal = $second instanceof \WC_Order ? (float) $second->get_total() : 0.0;
                $comparison  = $firstTotal <=> $secondTotal;

                return $sort === 'amount_asc' ? $comparison : -$comparison;
            });

            $total    = count($allOrders);
            $maxPages = max(1, (int) ceil($total / self::PER_PAGE));
            $orders   = array_slice($allOrders, ($currentPage - 1) * self::PER_PAGE, self::PER_PAGE);
        } else {
            [$query['orderby'], $query['order']] = $this->sortArguments($sort);
            $query['limit']    = self::PER_PAGE;
            $query['paged']    = $currentPage;
            $query['paginate'] = true;
            $results           = wc_get_orders($query);
            $orders            = is_object($results) && isset($results->orders) && is_array($results->orders)
                ? $results->orders
                : [];
            $total             = is_object($results) && isset($results->total)
                ? (int) $results->total
                : count($orders);
            $maxPages          = is_object($results) && isset($results->max_num_pages)
                ? max(1, (int) $results->max_num_pages)
                : 1;
        }

        ob_start();
        ?>
        <section class="am-account-orders" aria-labelledby="am-account-orders-title">
            <header class="am-account-orders__header">
                <div class="am-account-orders__heading">
                    <span class="am-account-orders__eyebrow"><?php echo esc_html__('Historia zakupów', 'am-toolkit'); ?></span>
                    <h1 id="am-account-orders-title" class="am-account-orders__title"><?php echo esc_html__('Moje zamówienia', 'am-toolkit'); ?></h1>
                    <p class="am-account-orders__intro"><?php echo esc_html__('Zarządzaj swoimi zakupami i pobieraj produkty cyfrowe.', 'am-toolkit'); ?></p>
                </div>

                <div class="am-account-orders__toolbar" aria-label="<?php echo esc_attr__('Filtrowanie i sortowanie zamówień', 'am-toolkit'); ?>">
                    <?php echo $this->filterControl($status); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    <?php echo $this->sortControl($sort); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                </div>
            </header>

            <?php if ($orders === []) : ?>
                <div class="am-account-orders__empty">
                    <span class="am-account-orders__empty-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" focusable="false">
                            <path d="M6 7h12l-1 13H7L6 7Zm3 0V5a3 3 0 0 1 6 0v2" />
                        </svg>
                    </span>
                    <div>
                        <h2><?php echo esc_html($status === '' ? __('Nie masz jeszcze żadnych zamówień', 'am-toolkit') : __('Brak zamówień spełniających wybrany filtr', 'am-toolkit')); ?></h2>
                        <p><?php echo esc_html($status === '' ? __('Gdy złożysz pierwsze zamówienie, jego szczegóły pojawią się właśnie tutaj.', 'am-toolkit') : __('Wybierz inny status albo pokaż wszystkie zamówienia.', 'am-toolkit')); ?></p>
                    </div>
                    <?php if ($status !== '') : ?>
                        <a href="<?php echo esc_url($this->ordersUrl()); ?>"><?php echo esc_html__('Pokaż wszystkie', 'am-toolkit'); ?></a>
                    <?php endif; ?>
                </div>
            <?php else : ?>
                <div class="am-account-orders__table-wrap">
                    <table class="am-account-orders__table">
                        <thead>
                            <tr>
                                <th scope="col"><?php echo esc_html__('ID zamówienia', 'am-toolkit'); ?></th>
                                <th scope="col"><?php echo esc_html__('Data', 'am-toolkit'); ?></th>
                                <th scope="col"><?php echo esc_html__('Produkt', 'am-toolkit'); ?></th>
                                <th scope="col"><?php echo esc_html__('Status', 'am-toolkit'); ?></th>
                                <th scope="col"><?php echo esc_html__('Kwota', 'am-toolkit'); ?></th>
                                <th scope="col"><?php echo esc_html__('Akcje', 'am-toolkit'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($orders as $order) : ?>
                                <?php if (!($order instanceof \WC_Order)) { continue; } ?>
                                <tr>
                                    <td data-label="<?php echo esc_attr__('Zamówienie', 'am-toolkit'); ?>">
                                        <a class="am-account-order__number" href="<?php echo esc_url($order->get_view_order_url()); ?>">
                                            #<?php echo esc_html($order->get_order_number()); ?>
                                        </a>
                                    </td>
                                    <td data-label="<?php echo esc_attr__('Data', 'am-toolkit'); ?>">
                                        <time datetime="<?php echo esc_attr($order->get_date_created() ? $order->get_date_created()->date('c') : ''); ?>">
                                            <?php echo esc_html($order->get_date_created() ? wc_format_datetime($order->get_date_created(), 'j M Y') : '—'); ?>
                                        </time>
                                    </td>
                                    <td class="am-account-order__products" data-label="<?php echo esc_attr__('Produkty', 'am-toolkit'); ?>">
                                        <?php echo $this->orderProducts($order); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                                    </td>
                                    <td data-label="<?php echo esc_attr__('Status', 'am-toolkit'); ?>">
                                        <span class="am-account-order__status am-account-order__status--<?php echo esc_attr(sanitize_html_class($order->get_status())); ?>">
                                            <span aria-hidden="true"></span>
                                            <?php echo esc_html(wc_get_order_status_name($order->get_status())); ?>
                                        </span>
                                    </td>
                                    <td class="am-account-order__total" data-label="<?php echo esc_attr__('Kwota', 'am-toolkit'); ?>">
                                        <?php echo wp_kses_post($order->get_formatted_order_total()); ?>
                                    </td>
                                    <td class="am-account-order__actions" data-label="<?php echo esc_attr__('Akcje', 'am-toolkit'); ?>">
                                        <div class="am-account-order__actions-inner">
                                            <?php echo $this->orderActions($order); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <footer class="am-account-orders__footer">
                    <p>
                        <?php
                        $first = (($currentPage - 1) * self::PER_PAGE) + 1;
                        $last  = min($total, $currentPage * self::PER_PAGE);
                        echo esc_html(sprintf(
                            __('Wyświetlono %1$d–%2$d z %3$d zamówień', 'am-toolkit'),
                            $first,
                            $last,
                            $total
                        ));
                        ?>
                    </p>
                    <?php echo $this->pagination($currentPage, $maxPages, $status, $sort); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                </footer>
            <?php endif; ?>
        </section>
        <?php

        return (string) ob_get_clean();
    }

    private function currentPage(): int
    {
        $endpointPage = absint(get_query_var(self::ENDPOINT));
        return max(1, $endpointPage);
    }

    private function selectedStatus(): string
    {
        $status = isset($_GET['amt_order_status'])
            ? sanitize_key(wp_unslash($_GET['amt_order_status']))
            : '';

        return array_key_exists('wc-' . $status, wc_get_order_statuses()) ? $status : '';
    }

    private function selectedSort(): string
    {
        $sort = isset($_GET['amt_order_sort'])
            ? sanitize_key(wp_unslash($_GET['amt_order_sort']))
            : 'newest';

        return array_key_exists($sort, $this->sortOptions()) ? $sort : 'newest';
    }

    /** @return array{0: string, 1: string} */
    private function sortArguments(string $sort): array
    {
        return match ($sort) {
            'oldest'      => ['date', 'ASC'],
            default       => ['date', 'DESC'],
        };
    }

    /** @return array<string, string> */
    private function sortOptions(): array
    {
        return [
            'newest'      => __('Najnowsze', 'am-toolkit'),
            'oldest'      => __('Najstarsze', 'am-toolkit'),
            'amount_desc' => __('Kwota: od najwyższej', 'am-toolkit'),
            'amount_asc'  => __('Kwota: od najniższej', 'am-toolkit'),
        ];
    }

    private function filterControl(string $selected): string
    {
        $statuses = ['' => __('Wszystkie statusy', 'am-toolkit')];

        foreach (wc_get_order_statuses() as $key => $label) {
            $statuses[substr($key, 3)] = $label;
        }

        return $this->toolbarControl(
            'filter',
            __('Filtruj', 'am-toolkit'),
            $statuses,
            $selected,
            'amt_order_status'
        );
    }

    private function sortControl(string $selected): string
    {
        return $this->toolbarControl(
            'sort',
            __('Sortuj', 'am-toolkit'),
            $this->sortOptions(),
            $selected,
            'amt_order_sort'
        );
    }

    /**
     * @param array<string, string> $options
     */
    private function toolbarControl(
        string $type,
        string $label,
        array $options,
        string $selected,
        string $queryKey
    ): string {
        ob_start();
        ?>
        <details class="am-account-orders__control am-account-orders__control--<?php echo esc_attr($type); ?>">
            <summary>
                <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                    <?php if ($type === 'filter') : ?>
                        <path d="M4 6h16M7 12h10m-7 6h4" />
                    <?php else : ?>
                        <path d="M5 7h14M5 12h10M5 17h6" />
                    <?php endif; ?>
                </svg>
                <span><?php echo esc_html($label); ?></span>
            </summary>
            <div class="am-account-orders__control-menu">
                <?php foreach ($options as $value => $optionLabel) : ?>
                    <?php
                    $url = $this->ordersUrl();
                    $otherKey = $queryKey === 'amt_order_status' ? 'amt_order_sort' : 'amt_order_status';
                    $otherValue = $queryKey === 'amt_order_status' ? $this->selectedSort() : $this->selectedStatus();

                    if ($value !== '' && !($queryKey === 'amt_order_sort' && $value === 'newest')) {
                        $url = add_query_arg($queryKey, $value, $url);
                    }

                    if ($otherValue !== '' && !($otherKey === 'amt_order_sort' && $otherValue === 'newest')) {
                        $url = add_query_arg($otherKey, $otherValue, $url);
                    }
                    ?>
                    <a class="<?php echo $value === $selected ? 'is-active' : ''; ?>" href="<?php echo esc_url($url); ?>">
                        <?php echo esc_html($optionLabel); ?>
                        <?php if ($value === $selected) : ?><span aria-hidden="true">✓</span><?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </details>
        <?php

        return (string) ob_get_clean();
    }

    private function orderProducts(\WC_Order $order): string
    {
        $items = array_values($order->get_items('line_item'));
        $shown = array_slice($items, 0, 3);

        ob_start();
        ?>
        <ul>
            <?php foreach ($shown as $item) : ?>
                <?php
                $product = $item->get_product();
                $name    = $item->get_name();
                $type    = $product ? $this->productTypeLabel($product->get_id()) : __('Produkt', 'am-toolkit');
                ?>
                <li>
                    <span><?php echo esc_html($name); ?></span>
                    <small><?php echo esc_html($type); ?></small>
                </li>
            <?php endforeach; ?>
        </ul>
        <?php if (count($items) > count($shown)) : ?>
            <span class="am-account-order__more-products">
                <?php echo esc_html(sprintf(__('+ %d więcej', 'am-toolkit'), count($items) - count($shown))); ?>
            </span>
        <?php endif; ?>
        <?php

        return (string) ob_get_clean();
    }

    private function productTypeLabel(int $productId): string
    {
        $categories = [
            'consultacje-i-mentoring'   => __('Konsultacja', 'am-toolkit'),
            'kursy-online'              => __('Kurs online', 'am-toolkit'),
            'workbooki-e-booki-szablony' => __('Produkt cyfrowy', 'am-toolkit'),
        ];

        foreach ($categories as $slug => $label) {
            if ($this->belongsToProductCategory($productId, $slug)) {
                return $label;
            }
        }

        return __('Produkt', 'am-toolkit');
    }

    private function belongsToProductCategory(int $productId, string $categorySlug): bool
    {
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

    private function orderActions(\WC_Order $order): string
    {
        $downloads = $this->orderDownloads($order->get_id());

        ob_start();
        ?>
        <a class="am-account-order__details" href="<?php echo esc_url($order->get_view_order_url()); ?>">
            <?php echo esc_html__('Szczegóły', 'am-toolkit'); ?>
        </a>

        <?php if ($order->needs_payment()) : ?>
            <a class="am-account-order__pay" href="<?php echo esc_url($order->get_checkout_payment_url()); ?>">
                <?php echo esc_html__('Zapłać', 'am-toolkit'); ?>
            </a>
        <?php endif; ?>

        <?php foreach ($downloads as $download) : ?>
            <a class="am-account-order__download" href="<?php echo esc_url($download['url']); ?>">
                <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                    <path d="M12 3v12m0 0 4.5-4.5M12 15l-4.5-4.5M5 20h14" />
                </svg>
                <span><?php echo esc_html(count($downloads) === 1 ? __('Pobierz', 'am-toolkit') : $download['name']); ?></span>
            </a>
        <?php endforeach; ?>
        <?php

        return (string) ob_get_clean();
    }

    /** @return array<int, array{name: string, url: string}> */
    private function orderDownloads(int $orderId): array
    {
        if ($this->downloadsByOrder === null) {
            $this->downloadsByOrder = [];

            if (function_exists('wc_get_customer_available_downloads')) {
                foreach (wc_get_customer_available_downloads(get_current_user_id()) as $download) {
                    $downloadOrderId = isset($download['order_id']) ? (int) $download['order_id'] : 0;
                    $url             = isset($download['download_url']) ? (string) $download['download_url'] : '';

                    if ($downloadOrderId <= 0 || $url === '') {
                        continue;
                    }

                    $file = isset($download['file']) && is_array($download['file'])
                        ? $download['file']
                        : [];
                    $name = (string) ($file['name'] ?? $download['download_name'] ?? __('Pobierz plik', 'am-toolkit'));

                    $this->downloadsByOrder[$downloadOrderId][] = [
                        'name' => sanitize_text_field($name),
                        'url'  => $url,
                    ];
                }
            }
        }

        return $this->downloadsByOrder[$orderId] ?? [];
    }

    private function pagination(
        int $currentPage,
        int $maxPages,
        string $status,
        string $sort
    ): string {
        if ($maxPages <= 1) {
            return '';
        }

        $pages = array_unique(array_filter([
            1,
            $currentPage - 1,
            $currentPage,
            $currentPage + 1,
            $maxPages,
        ], static fn(int $page): bool => $page >= 1 && $page <= $maxPages));
        sort($pages);

        ob_start();
        ?>
        <nav class="am-account-orders__pagination" aria-label="<?php echo esc_attr__('Strony zamówień', 'am-toolkit'); ?>">
            <?php if ($currentPage > 1) : ?>
                <a aria-label="<?php echo esc_attr__('Poprzednia strona', 'am-toolkit'); ?>" href="<?php echo esc_url($this->pageUrl($currentPage - 1, $status, $sort)); ?>">‹</a>
            <?php else : ?>
                <span class="is-disabled" aria-hidden="true">‹</span>
            <?php endif; ?>

            <?php $previous = 0; ?>
            <?php foreach ($pages as $page) : ?>
                <?php if ($previous > 0 && $page > $previous + 1) : ?><span class="am-account-orders__ellipsis">…</span><?php endif; ?>
                <?php if ($page === $currentPage) : ?>
                    <span class="is-current" aria-current="page"><?php echo esc_html((string) $page); ?></span>
                <?php else : ?>
                    <a href="<?php echo esc_url($this->pageUrl($page, $status, $sort)); ?>"><?php echo esc_html((string) $page); ?></a>
                <?php endif; ?>
                <?php $previous = $page; ?>
            <?php endforeach; ?>

            <?php if ($currentPage < $maxPages) : ?>
                <a aria-label="<?php echo esc_attr__('Następna strona', 'am-toolkit'); ?>" href="<?php echo esc_url($this->pageUrl($currentPage + 1, $status, $sort)); ?>">›</a>
            <?php else : ?>
                <span class="is-disabled" aria-hidden="true">›</span>
            <?php endif; ?>
        </nav>
        <?php

        return (string) ob_get_clean();
    }

    private function pageUrl(int $page, string $status, string $sort): string
    {
        $url = wc_get_endpoint_url(self::ENDPOINT, $page > 1 ? (string) $page : '', wc_get_page_permalink('myaccount'));

        if ($status !== '') {
            $url = add_query_arg('amt_order_status', $status, $url);
        }

        if ($sort !== 'newest') {
            $url = add_query_arg('amt_order_sort', $sort, $url);
        }

        return $url;
    }

    private function ordersUrl(): string
    {
        return wc_get_endpoint_url(self::ENDPOINT, '', wc_get_page_permalink('myaccount'));
    }
}
