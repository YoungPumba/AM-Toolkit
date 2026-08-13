<?php

declare(strict_types=1);

/**
 * Static-analysis bootstrap.
 *
 * This file models the small part of the WordPress and WooCommerce runtime
 * that AM Toolkit references directly. It is loaded by PHPStan only and is
 * never included by the plugin in WordPress.
 */

require_once dirname(__DIR__) . '/vendor/php-stubs/wordpress-stubs/wordpress-stubs.php';

defined('ABSPATH') || define(
    'ABSPATH',
    __DIR__ . DIRECTORY_SEPARATOR . 'wordpress' . DIRECTORY_SEPARATOR
);
defined('ARRAY_A') || define('ARRAY_A', 'ARRAY_A');
defined('AM_TOOLKIT_PATH') || define('AM_TOOLKIT_PATH', dirname(__DIR__) . DIRECTORY_SEPARATOR);
defined('AM_TOOLKIT_URL') || define('AM_TOOLKIT_URL', 'https://example.test/wp-content/plugins/am-toolkit/');
defined('AM_TOOLKIT_VERSION') || define('AM_TOOLKIT_VERSION', '0.0.0-analysis');
defined('COOKIEHASH') || define('COOKIEHASH', 'analysis');
defined('EP_ROOT') || define('EP_ROOT', 1);
defined('EP_PAGES') || define('EP_PAGES', 4096);

if (!class_exists('WC_Product')) {
    class WC_Product
    {
        public function get_id(): int
        {
            return 0;
        }

        public function get_name(): string
        {
            return '';
        }

        public function get_permalink(): string
        {
            return '';
        }

        public function get_formatted_name(): string
        {
            return '';
        }

        public function get_parent_id(): int
        {
            return 0;
        }

        /**
         * @param string|array{0: int, 1: int} $size
         * @param array<string, scalar> $attr
         */
        public function get_image(
            string|array $size = 'woocommerce_thumbnail',
            array $attr = [],
            bool $placeholder = true
        ): string
        {
            return '';
        }

        public function is_visible(): bool
        {
            return true;
        }

        public function is_downloadable(): bool
        {
            return false;
        }

        /**
         * @param string|list<string> $type
         */
        public function is_type(string|array $type): bool
        {
            return false;
        }

        /**
         * @return array<string, WC_Product_Download>
         */
        public function get_downloads(): array
        {
            return [];
        }

        public function delete_meta_data(string $key): void
        {
        }

        public function update_meta_data(string $key, mixed $value): void
        {
        }
    }
}

if (!class_exists('WC_Product_Download')) {
    class WC_Product_Download
    {
        public function get_id(): string
        {
            return '';
        }

        public function get_name(): string
        {
            return '';
        }

        public function get_file(): string
        {
            return '';
        }

        public function get_enabled(): bool
        {
            return true;
        }
    }
}

if (!class_exists('WC_Order_Item_Product')) {
    class WC_Order_Item_Product
    {
        public function get_product(): WC_Product|false
        {
            return false;
        }

        public function get_product_id(): int
        {
            return 0;
        }

        public function get_variation_id(): int
        {
            return 0;
        }

        public function get_name(): string
        {
            return '';
        }

        public function get_total(): string
        {
            return '';
        }

        public function get_quantity(): int
        {
            return 0;
        }
    }
}

if (!class_exists('WC_DateTime')) {
    class WC_DateTime extends DateTime
    {
        public function date(string $format): string
        {
            return $this->format($format);
        }
    }
}

if (!class_exists('WC_Order')) {
    class WC_Order
    {
        public function get_id(): int
        {
            return 0;
        }

        public function get_order_number(): string
        {
            return '';
        }

        public function get_status(): string
        {
            return '';
        }

        public function is_paid(): bool
        {
            return false;
        }

        public function get_date_created(): ?WC_DateTime
        {
            return null;
        }

        public function get_user_id(): int
        {
            return 0;
        }

        public function get_payment_method_title(): string
        {
            return '';
        }

        public function get_item_count(string $itemType = 'line_item'): int
        {
            return 0;
        }

        /**
         * @return array<string, array{label: string, value: string}>
         */
        public function get_order_item_totals(string $taxDisplay = ''): array
        {
            return [];
        }

        public function needs_payment(): bool
        {
            return false;
        }

        public function get_customer_note(): string
        {
            return '';
        }

        public function get_formatted_line_subtotal(
            WC_Order_Item_Product $item,
            string $taxDisplay = ''
        ): string {
            return '';
        }

        public function get_formatted_billing_address(
            string $emptyContent = '',
            string $separator = '<br/>'
        ): string {
            return '';
        }

        public function get_formatted_shipping_address(
            string $emptyContent = '',
            string $separator = '<br/>'
        ): string {
            return '';
        }

        public function get_shipping_first_name(): string
        {
            return '';
        }

        public function get_shipping_last_name(): string
        {
            return '';
        }

        public function get_shipping_address_1(): string
        {
            return '';
        }

        public function get_shipping_city(): string
        {
            return '';
        }

        public function get_billing_email(): string
        {
            return '';
        }

        public function get_billing_phone(): string
        {
            return '';
        }

        public function get_formatted_order_total(): string
        {
            return '';
        }

        public function get_total(): string
        {
            return '';
        }

        public function get_view_order_url(): string
        {
            return '';
        }

        public function get_checkout_payment_url(): string
        {
            return '';
        }

        /**
         * @return list<WC_Order_Item_Product>
         */
        public function get_items(string $type = ''): array
        {
            return [];
        }
    }
}

if (!class_exists('WC_Subscription')) {
    class WC_Subscription extends WC_Order
    {
    }
}

if (!class_exists('WC_Customer')) {
    class WC_Customer
    {
        public function __construct(int $customerId = 0)
        {
        }

        public function get_id(): int
        {
            return 0;
        }
    }
}

if (!class_exists('WC_Countries')) {
    class WC_Countries
    {
        public function get_base_country(): string
        {
            return '';
        }

        /**
         * @return array<string, array<string, mixed>>
         */
        public function get_address_fields(string $country = '', string $prefix = ''): array
        {
            return [];
        }
    }
}

if (!class_exists('WC_Download_Handler')) {
    class WC_Download_Handler
    {
        public static function get_download_url(
            int $productId,
            string $downloadId,
            int $userId,
            string $userEmail
        ): string {
            return '';
        }

        public static function download(string $filePath, int|string $productId): void
        {
        }
    }
}

if (!class_exists('WC_AJAX')) {
    class WC_AJAX
    {
        public static function get_endpoint(string $request = ''): string
        {
            return '';
        }
    }
}

if (!class_exists('WC_Cart')) {
    class WC_Cart
    {
        public function get_cart_contents_count(): int
        {
            return 0;
        }

        public function get_cart_total(): string
        {
            return '';
        }

        /**
         * @return array<string, mixed>
         */
        public function get_cart_item(string $cartItemKey): array
        {
            return [];
        }
    }
}

if (!class_exists('WC_Session_Handler')) {
    class WC_Session_Handler
    {
        public function get(string $key, mixed $default = null): mixed
        {
            return $default;
        }

        public function set(string $key, mixed $value): void
        {
        }

        public function __unset(string $key): void
        {
        }
    }
}

if (!class_exists('WooCommerce')) {
    class WooCommerce
    {
        public ?WC_Cart $cart = null;

        public ?WC_Session_Handler $session = null;

        public ?WC_Countries $countries = null;
    }
}

if (!function_exists('wc_get_product')) {
    /**
     * @param int|string $productId
     */
    function wc_get_product(int|string $productId): WC_Product|false
    {
        return false;
    }
}

if (!function_exists('wc_get_cart_url')) {
    function wc_get_cart_url(): string
    {
        return '';
    }
}

if (!class_exists('WC_Order_Query_Result')) {
    class WC_Order_Query_Result
    {
        public mixed $orders = [];
        public mixed $total = 0;
        public mixed $max_num_pages = 1;
    }
}

if (!function_exists('wc_get_orders')) {
    /**
     * WooCommerce changes the returned element type according to the
     * `return` argument, so the analysis bootstrap deliberately keeps the
     * elements mixed while retaining the array contract.
     *
     * @param array<string, mixed> $args
     * @return array<int, mixed>|WC_Order_Query_Result
     */
    function wc_get_orders(array $args = []): array|WC_Order_Query_Result
    {
        return [];
    }
}

if (!function_exists('wc_get_order')) {
    function wc_get_order(int|string $orderId): WC_Order|false
    {
        return false;
    }
}

if (!function_exists('wc_get_order_statuses')) {
    /**
     * @return array<string, string>
     */
    function wc_get_order_statuses(): array
    {
        return [];
    }
}

if (!function_exists('wc_get_is_paid_statuses')) {
    /** @return list<string> */
    function wc_get_is_paid_statuses(): array
    {
        return ['processing', 'completed'];
    }
}

if (!function_exists('wcs_order_contains_subscription')) {
    /** @param string|list<string> $orderType */
    function wcs_order_contains_subscription(mixed $order, string|array $orderType = 'parent'): bool
    {
        return false;
    }
}

if (!function_exists('wc_get_order_status_name')) {
    function wc_get_order_status_name(string $status): string
    {
        return '';
    }
}

if (!function_exists('wc_get_page_permalink')) {
    function wc_get_page_permalink(string $page): string
    {
        return '';
    }
}

if (!function_exists('wc_get_endpoint_url')) {
    function wc_get_endpoint_url(string $endpoint, string $value = '', string $permalink = ''): string
    {
        return '';
    }
}

if (!function_exists('wc_get_account_endpoint_url')) {
    function wc_get_account_endpoint_url(string $endpoint): string
    {
        return '';
    }
}

if (!function_exists('wc_logout_url')) {
    function wc_logout_url(string $redirect = ''): string
    {
        return '';
    }
}

if (!function_exists('wc_format_datetime')) {
    function wc_format_datetime(?DateTimeInterface $date, string $format = ''): string
    {
        return '';
    }
}

if (!function_exists('wc_get_customer_order_count')) {
    function wc_get_customer_order_count(int $userId): int
    {
        return 0;
    }
}

if (!function_exists('wc_get_customer_available_downloads')) {
    /**
     * @return list<array<string, mixed>>
     */
    function wc_get_customer_available_downloads(int $customerId): array
    {
        return [];
    }
}

if (!function_exists('wc_display_item_meta')) {
    /**
     * @param array<string, mixed> $args
     */
    function wc_display_item_meta(WC_Order_Item_Product $item, array $args = []): string
    {
        return '';
    }
}

if (!function_exists('wc_placeholder_img')) {
    /**
     * @param array<string, string> $attr
     */
    function wc_placeholder_img(string $size = 'woocommerce_thumbnail', array $attr = []): string
    {
        return '';
    }
}

if (!function_exists('wc_add_notice')) {
    function wc_add_notice(string $message, string $noticeType = 'success'): void
    {
    }
}

if (!function_exists('wc_print_notices')) {
    function wc_print_notices(bool $return = false): string|null
    {
        return $return ? '' : null;
    }
}

if (!function_exists('wc_notice_count')) {
    function wc_notice_count(string $noticeType = ''): int
    {
        return 0;
    }
}

if (!function_exists('wc_sanitize_phone')) {
    function wc_sanitize_phone(string $phone): string
    {
        return $phone;
    }
}

if (!function_exists('wc_sanitize_phone_number')) {
    function wc_sanitize_phone_number(string $phone): string
    {
        return $phone;
    }
}

if (!function_exists('wc_nocache_headers')) {
    function wc_nocache_headers(): void
    {
    }
}

if (!function_exists('wc_clean')) {
    function wc_clean(mixed $value): mixed
    {
        return $value;
    }
}

if (!function_exists('woocommerce_form_field')) {
    /**
     * @param array<string, mixed> $args
     */
    function woocommerce_form_field(string $key, array $args, mixed $value = null): string
    {
        return '';
    }
}

if (!function_exists('WC')) {
    function WC(): WooCommerce
    {
        return new WooCommerce();
    }
}
