<?php

namespace AMToolkit\Modules\WooCommerce;

use AMToolkit\Settings\Notifications;

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

        $settings = Notifications::get();

        wp_localize_script(
            self::SCRIPT_HANDLE,
            'AMToolkitWooCommerce',
            [
                'cartUrl'        => wc_get_cart_url(),
                'pending'        => $this->pullNotifications(),
                'addedEnabled'   => (int) $settings['added_enabled'],
                'removedEnabled' => (int) $settings['removed_enabled'],
                'duration'       => (int) $settings['duration'],
                'labels'         => [
                    'addedTitle'     => $settings['added_title'],
                    'addedMessage'   => $settings['added_message'],
                    'removedTitle'   => $settings['removed_title'],
                    'removedMessage' => $settings['removed_message'],
                    'cartAction'     => $settings['cart_action'],
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

        $settings = Notifications::get();

        if (empty($settings['added_enabled'])) {
            return;
        }

        $product = wc_get_product($variationId ?: $productId);

        if (!$product) {
            return;
        }

        $this->pushNotification([
            'type'       => 'success',
            'title'      => $settings['added_title'],
            'message'    => Notifications::formatMessage($settings['added_message'], $product->get_name()),
            'actionText' => $settings['cart_action'],
            'actionUrl'  => wc_get_cart_url(),
            'duration'   => (int) $settings['duration'],
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

        $settings = Notifications::get();

        if (empty($settings['removed_enabled'])) {
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
            'title'    => $settings['removed_title'],
            'message'  => Notifications::formatMessage($settings['removed_message'], $productName),
            'duration' => (int) $settings['duration'],
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
        $product = $cartItem['data'] ?? null;

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
        $notification['_createdAt'] = time();
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

        if (!is_array($notifications)) {
            return [];
        }

        $unique = [];
        $now = time();

        foreach ($notifications as $notification) {
            if (!is_array($notification)) {
                continue;
            }

            $createdAt = (int) ($notification['_createdAt'] ?? $now);

            if (($now - $createdAt) > 30) {
                continue;
            }

            unset($notification['_createdAt']);
            $fingerprint = md5(wp_json_encode($notification));
            $unique[$fingerprint] = $notification;
        }

        return array_values($unique);
    }

    private function isAsyncRequest(): bool
    {
        return wp_doing_ajax()
            || (defined('REST_REQUEST') && REST_REQUEST);
    }
}
