<?php

namespace AMToolkit\Modules\WooCommerce;

if (!defined('ABSPATH')) {
    exit;
}

final class ToastIntegration
{
    private const SESSION_KEY = 'am_toolkit_toast_notifications';
    private const SCRIPT_HANDLE = 'am-toolkit-woocommerce-toast';

    private bool $suppressRemovedNotice = false;

    public function boot(): void
    {
        add_action('wp_enqueue_scripts', [$this, 'enqueue'], 20);

        add_action('woocommerce_add_to_cart', [$this, 'captureAddedProduct'], 20, 6);
        add_action('woocommerce_remove_cart_item', [$this, 'captureRemovedProduct'], 20, 2);

        add_filter('wc_add_to_cart_message_html', '__return_empty_string', 100);
        add_filter('woocommerce_add_success', [$this, 'suppressRemovedNotice'], 100);
        add_filter('woocommerce_cart_item_remove_link', [$this, 'addProductNameToRemoveLink'], 20, 2);
    }

    public function enqueue(): void
    {
        if (is_admin()) {
            return;
        }

        $relativePath = 'assets/js/woocommerce-toast.js';
        $absolutePath = AM_TOOLKIT_PATH . $relativePath;
        $version = file_exists($absolutePath)
            ? (string) filemtime($absolutePath)
            : AM_TOOLKIT_VERSION;

        wp_enqueue_script(
            self::SCRIPT_HANDLE,
            AM_TOOLKIT_URL . $relativePath,
            ['jquery', 'am-toolkit-toast'],
            $version,
            true
        );

        wp_localize_script(
            self::SCRIPT_HANDLE,
            'AMToolkitWooCommerce',
            [
                'cartUrl' => wc_get_cart_url(),
                'pending' => $this->pullNotifications(),
                'labels'  => [
                    'addedTitle'    => __('Dodano do koszyka', 'am-toolkit'),
                    'addedFallback' => __('Produkt został dodany do koszyka.', 'am-toolkit'),
                    'removedTitle'  => __('Usunięto z koszyka', 'am-toolkit'),
                    'removedFallback' => __('Produkt został usunięty z koszyka.', 'am-toolkit'),
                    'cartAction'    => __('Przejdź do koszyka →', 'am-toolkit'),
                ],
            ]
        );
    }

    /**
     * Stores a toast for classic add-to-cart links that reload the page.
     */
    public function captureAddedProduct(
        string $cartItemKey,
        int $productId,
        $quantity,
        int $variationId,
        array $variation,
        array $cartItemData
    ): void {
        if ($this->isAsyncRequest()) {
            return;
        }

        $product = wc_get_product($variationId ?: $productId);

        if (!$product) {
            return;
        }

        $this->pushNotification([
            'type'       => 'success',
            'title'      => __('Dodano do koszyka', 'am-toolkit'),
            'message'    => sprintf(
                /* translators: %s: product name. */
                __('Produkt „%s” został dodany do koszyka.', 'am-toolkit'),
                $product->get_name()
            ),
            'actionText' => __('Przejdź do koszyka →', 'am-toolkit'),
            'actionUrl'  => wc_get_cart_url(),
            'duration'   => 4000,
        ]);
    }

    /**
     * Stores a toast for classic remove links and marks the WC notice for removal.
     */
    public function captureRemovedProduct(string $cartItemKey, object $cart): void
    {
        $this->suppressRemovedNotice = true;

        if ($this->isAsyncRequest()) {
            return;
        }

        $cartItem = $cart->removed_cart_contents[$cartItemKey] ?? null;

        if (!is_array($cartItem)) {
            return;
        }

        $product = $cartItem['data'] ?? null;

        if (!$product || !is_a($product, 'WC_Product')) {
            $productId = (int) ($cartItem['variation_id'] ?? 0);

            if ($productId <= 0) {
                $productId = (int) ($cartItem['product_id'] ?? 0);
            }

            $product = wc_get_product($productId);
        }

        $productName = $product ? $product->get_name() : __('Produkt', 'am-toolkit');

        $this->pushNotification([
            'type'     => 'info',
            'title'    => __('Usunięto z koszyka', 'am-toolkit'),
            'message'  => sprintf(
                /* translators: %s: product name. */
                __('Produkt „%s” został usunięty z koszyka.', 'am-toolkit'),
                $productName
            ),
            'duration' => 4000,
        ]);
    }

    /**
     * Removes only the success notice generated for a cart-item removal.
     */
    public function suppressRemovedNotice(string $message): string
    {
        if (!$this->suppressRemovedNotice) {
            return $message;
        }

        $this->suppressRemovedNotice = false;

        return '';
    }

    /**
     * Adds the product name to AJAX remove links for the browser event.
     */
    public function addProductNameToRemoveLink(string $link, string $cartItemKey): string
    {
        if (!function_exists('WC') || !WC()->cart) {
            return $link;
        }

        $cartItem = WC()->cart->get_cart_item($cartItemKey);
        $product = is_array($cartItem) ? ($cartItem['data'] ?? null) : null;

        if (!$product || !is_a($product, 'WC_Product')) {
            return $link;
        }

        return str_replace(
            '<a ',
            '<a data-amt-product-name="' . esc_attr($product->get_name()) . '" ',
            $link
        );
    }

    private function pushNotification(array $notification): void
    {
        if (!function_exists('WC') || !WC()->session) {
            return;
        }

        $notifications = WC()->session->get(self::SESSION_KEY, []);
        $notifications = is_array($notifications) ? $notifications : [];
        $notifications[] = $notification;

        WC()->session->set(self::SESSION_KEY, $notifications);
    }

    private function pullNotifications(): array
    {
        if (!function_exists('WC') || !WC()->session) {
            return [];
        }

        $notifications = WC()->session->get(self::SESSION_KEY, []);
        WC()->session->__unset(self::SESSION_KEY);

        return is_array($notifications) ? $notifications : [];
    }

    private function isAsyncRequest(): bool
    {
        return wp_doing_ajax()
            || (defined('REST_REQUEST') && REST_REQUEST);
    }
}
