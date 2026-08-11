<?php

namespace AMToolkit\Modules\Account;

defined('ABSPATH') || exit;

final class AccountOrderDetails
{
    private const ENDPOINT = 'view-order';

    public function boot(): void
    {
        add_shortcode('am_account_order_details', [$this, 'render']);
        add_filter('template_include', [$this, 'accountTemplate'], 101);
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

        $pluginTemplate = AM_TOOLKIT_PATH . 'templates/account/order-details.php';

        return file_exists($pluginTemplate) ? $pluginTemplate : $template;
    }

    public function render(): string
    {
        if (!is_user_logged_in() || !function_exists('wc_get_order')) {
            return '';
        }

        $orderId = absint(get_query_var(self::ENDPOINT));
        $order   = $orderId ? wc_get_order($orderId) : false;

        if (
            !($order instanceof \WC_Order) ||
            (int) $order->get_user_id() !== get_current_user_id()
        ) {
            return $this->renderUnavailable();
        }

        $created = $order->get_date_created();

        ob_start();
        ?>
        <section class="am-order-details" aria-labelledby="am-order-details-title">
            <header class="am-order-details__header">
                <div class="am-order-details__heading">
                    <a class="am-order-details__back" href="<?php echo esc_url($this->ordersUrl()); ?>">
                        <span aria-hidden="true">←</span>
                        <?php echo esc_html__('Wszystkie zamówienia', 'am-toolkit'); ?>
                    </a>
                    <span class="am-order-details__eyebrow"><?php echo esc_html__('Szczegóły zakupu', 'am-toolkit'); ?></span>
                    <h1 id="am-order-details-title" class="am-order-details__title">
                        <?php echo esc_html(sprintf(__('Zamówienie #%s', 'am-toolkit'), $order->get_order_number())); ?>
                    </h1>
                    <p class="am-order-details__intro">
                        <?php echo esc_html__('Tutaj znajdziesz produkty, płatność, adresy oraz udostępnione pliki.', 'am-toolkit'); ?>
                    </p>
                </div>

                <span class="am-account-order__status am-account-order__status--<?php echo esc_attr(sanitize_html_class($order->get_status())); ?>">
                    <span aria-hidden="true"></span>
                    <?php echo esc_html(wc_get_order_status_name($order->get_status())); ?>
                </span>
            </header>

            <div class="am-order-details__summary" aria-label="<?php echo esc_attr__('Podsumowanie zamówienia', 'am-toolkit'); ?>">
                <?php echo $this->summaryItem(__('Data zamówienia', 'am-toolkit'), $created ? wc_format_datetime($created, 'j F Y') : '—', 'calendar'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                <?php echo $this->summaryItem(__('Wartość', 'am-toolkit'), wp_strip_all_tags($order->get_formatted_order_total()), 'total'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                <?php echo $this->summaryItem(__('Płatność', 'am-toolkit'), $order->get_payment_method_title() ?: __('Nie określono', 'am-toolkit'), 'payment'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                <?php echo $this->summaryItem(__('Liczba pozycji', 'am-toolkit'), (string) $order->get_item_count(), 'items'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            </div>

            <div class="am-order-details__content">
                <section class="am-order-details__products" aria-labelledby="am-order-products-title">
                    <header class="am-order-details__section-header">
                        <div>
                            <span><?php echo esc_html__('Twoje zakupy', 'am-toolkit'); ?></span>
                            <h2 id="am-order-products-title"><?php echo esc_html__('Produkty w zamówieniu', 'am-toolkit'); ?></h2>
                        </div>
                        <span class="am-order-details__count"><?php echo esc_html((string) $order->get_item_count()); ?></span>
                    </header>

                    <div class="am-order-details__product-list">
                        <?php foreach ($order->get_items('line_item') as $item) : ?>
                            <?php echo $this->renderProduct($order, $item); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                        <?php endforeach; ?>
                    </div>
                </section>

                <aside class="am-order-details__totals" aria-labelledby="am-order-totals-title">
                    <header class="am-order-details__section-header">
                        <div>
                            <span><?php echo esc_html__('Rozliczenie', 'am-toolkit'); ?></span>
                            <h2 id="am-order-totals-title"><?php echo esc_html__('Podsumowanie kwot', 'am-toolkit'); ?></h2>
                        </div>
                    </header>

                    <dl class="am-order-details__totals-list">
                        <?php foreach ($order->get_order_item_totals() as $total) : ?>
                            <div>
                                <dt><?php echo wp_kses_post($total['label']); ?></dt>
                                <dd><?php echo wp_kses_post($total['value']); ?></dd>
                            </div>
                        <?php endforeach; ?>
                    </dl>

                    <?php if ($order->needs_payment()) : ?>
                        <a class="am-order-details__pay" href="<?php echo esc_url($order->get_checkout_payment_url()); ?>">
                            <?php echo esc_html__('Opłać zamówienie', 'am-toolkit'); ?>
                        </a>
                    <?php endif; ?>
                </aside>
            </div>

            <?php echo $this->renderAddresses($order); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

            <?php if ($order->get_customer_note() !== '') : ?>
                <section class="am-order-details__note" aria-labelledby="am-order-note-title">
                    <span aria-hidden="true">i</span>
                    <div>
                        <h2 id="am-order-note-title"><?php echo esc_html__('Informacja do zamówienia', 'am-toolkit'); ?></h2>
                        <p><?php echo esc_html($order->get_customer_note()); ?></p>
                    </div>
                </section>
            <?php endif; ?>
        </section>
        <?php

        return (string) ob_get_clean();
    }

    private function renderProduct(\WC_Order $order, \WC_Order_Item_Product $item): string
    {
        $product     = $item->get_product();
        $productId   = $product ? $product->get_id() : $item->get_product_id();
        $image       = $product
            ? $product->get_image('woocommerce_thumbnail', [
                'class'   => 'am-order-product__image-source',
                'loading' => 'lazy',
            ])
            : wc_placeholder_img('woocommerce_thumbnail');
        $productUrl  = $product && $product->is_visible() ? $product->get_permalink() : '';
        $downloads   = $this->downloadsForItem($order->get_id(), $productId, $item->get_product_id());
        $itemMeta    = wc_display_item_meta($item, [
            'echo'      => false,
            'separator' => ', ',
        ]);

        ob_start();
        ?>
        <article class="am-order-product">
            <div class="am-order-product__image">
                <?php echo $image; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            </div>
            <div class="am-order-product__body">
                <div class="am-order-product__heading">
                    <div>
                        <span class="am-order-product__quantity">
                            <?php echo esc_html(sprintf(__('Ilość: %d', 'am-toolkit'), $item->get_quantity())); ?>
                        </span>
                        <h3>
                            <?php if ($productUrl !== '') : ?>
                                <a href="<?php echo esc_url($productUrl); ?>"><?php echo esc_html($item->get_name()); ?></a>
                            <?php else : ?>
                                <?php echo esc_html($item->get_name()); ?>
                            <?php endif; ?>
                        </h3>
                    </div>
                    <span class="am-order-product__price">
                        <?php echo wp_kses_post($order->get_formatted_line_subtotal($item)); ?>
                    </span>
                </div>

                <?php if ($itemMeta !== '') : ?>
                    <div class="am-order-product__meta"><?php echo wp_kses_post($itemMeta); ?></div>
                <?php endif; ?>

                <?php if ($downloads !== []) : ?>
                    <div class="am-order-product__downloads" aria-label="<?php echo esc_attr(sprintf(__('Pliki produktu: %s', 'am-toolkit'), $item->get_name())); ?>">
                        <?php foreach ($downloads as $download) : ?>
                            <a href="<?php echo esc_url($download['url']); ?>">
                                <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                    <path d="M12 3v12m0 0 4.5-4.5M12 15l-4.5-4.5M5 20h14" />
                                </svg>
                                <span><?php echo esc_html($download['name']); ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </article>
        <?php

        return (string) ob_get_clean();
    }

    private function renderAddresses(\WC_Order $order): string
    {
        $billing = $order->get_formatted_billing_address(__('Nie podano adresu rozliczeniowego.', 'am-toolkit'));
        $shipping = $order->get_formatted_shipping_address(__('Nie podano adresu dostawy.', 'am-toolkit'));
        $showShipping = (
            $order->get_shipping_first_name() !== '' ||
            $order->get_shipping_last_name() !== '' ||
            $order->get_shipping_address_1() !== '' ||
            $order->get_shipping_city() !== ''
        );

        ob_start();
        ?>
        <section class="am-order-details__addresses" aria-labelledby="am-order-addresses-title">
            <header class="am-order-details__section-header">
                <div>
                    <span><?php echo esc_html__('Dane klienta', 'am-toolkit'); ?></span>
                    <h2 id="am-order-addresses-title"><?php echo esc_html__('Adresy zamówienia', 'am-toolkit'); ?></h2>
                </div>
            </header>

            <div class="am-order-details__address-grid">
                <article>
                    <h3><?php echo esc_html__('Dane rozliczeniowe', 'am-toolkit'); ?></h3>
                    <address><?php echo wp_kses_post($billing); ?></address>
                    <?php if ($order->get_billing_email() !== '') : ?>
                        <a href="mailto:<?php echo esc_attr($order->get_billing_email()); ?>"><?php echo esc_html($order->get_billing_email()); ?></a>
                    <?php endif; ?>
                    <?php if ($order->get_billing_phone() !== '') : ?>
                        <a href="tel:<?php echo esc_attr(preg_replace('/[^0-9+]/', '', $order->get_billing_phone())); ?>"><?php echo esc_html($order->get_billing_phone()); ?></a>
                    <?php endif; ?>
                </article>

                <?php if ($showShipping) : ?>
                    <article>
                        <h3><?php echo esc_html__('Adres dostawy', 'am-toolkit'); ?></h3>
                        <address><?php echo wp_kses_post($shipping); ?></address>
                    </article>
                <?php endif; ?>
            </div>
        </section>
        <?php

        return (string) ob_get_clean();
    }

    private function summaryItem(string $label, string $value, string $icon): string
    {
        $icons = [
            'calendar' => '<path d="M5 5h14v15H5V5Zm3-2v4m8-4v4M5 10h14"/>',
            'total'    => '<path d="M4 7h16v11H4V7Zm0 4h16m-4 4h1"/>',
            'payment'  => '<path d="M4 7h16v11H4V7Zm0 4h16m3-5v6"/>',
            'items'    => '<path d="M6 7h12l-1 13H7L6 7Zm3 0V5a3 3 0 0 1 6 0v2"/>',
        ];

        ob_start();
        ?>
        <div class="am-order-details__summary-item">
            <span class="am-order-details__summary-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" focusable="false"><?php echo $icons[$icon] ?? $icons['items']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></svg>
            </span>
            <div>
                <span><?php echo esc_html($label); ?></span>
                <strong><?php echo esc_html($value); ?></strong>
            </div>
        </div>
        <?php

        return (string) ob_get_clean();
    }

    /** @return array<int, array{name: string, url: string}> */
    private function downloadsForItem(int $orderId, int $productId, int $parentProductId): array
    {
        if (!function_exists('wc_get_customer_available_downloads')) {
            return [];
        }

        $downloads = [];

        foreach (wc_get_customer_available_downloads(get_current_user_id()) as $download) {
            $downloadOrderId = absint($download['order_id'] ?? 0);
            $downloadProductId = absint($download['product_id'] ?? 0);

            if (
                $downloadOrderId !== $orderId ||
                !in_array($downloadProductId, [$productId, $parentProductId], true)
            ) {
                continue;
            }

            $url = isset($download['download_url']) ? (string) $download['download_url'] : '';

            if ($url === '') {
                continue;
            }

            $file = isset($download['file']) && is_array($download['file']) ? $download['file'] : [];
            $name = (string) ($file['name'] ?? $download['download_name'] ?? __('Pobierz plik', 'am-toolkit'));

            $downloads[] = [
                'name' => sanitize_text_field($name),
                'url'  => $url,
            ];
        }

        return $downloads;
    }

    private function renderUnavailable(): string
    {
        ob_start();
        ?>
        <section class="am-order-details am-order-details--unavailable">
            <div class="am-order-details__unavailable-icon" aria-hidden="true">!</div>
            <h1><?php echo esc_html__('Nie znaleziono zamówienia', 'am-toolkit'); ?></h1>
            <p><?php echo esc_html__('Zamówienie nie istnieje albo nie jest przypisane do Twojego konta.', 'am-toolkit'); ?></p>
            <a href="<?php echo esc_url($this->ordersUrl()); ?>"><?php echo esc_html__('Wróć do zamówień', 'am-toolkit'); ?></a>
        </section>
        <?php

        return (string) ob_get_clean();
    }

    private function ordersUrl(): string
    {
        return wc_get_endpoint_url('orders', '', wc_get_page_permalink('myaccount'));
    }
}
