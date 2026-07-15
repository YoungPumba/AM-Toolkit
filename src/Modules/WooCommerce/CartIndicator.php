<?php

namespace AMToolkit\Modules\WooCommerce;

if (!defined('ABSPATH')) {
    exit;
}

final class CartIndicator
{
    private const STYLE_HANDLE = 'am-toolkit-cart';
    private const SCRIPT_HANDLE = 'am-toolkit-cart';

    public function boot(): void
    {
        add_shortcode('custom_cart', [$this, 'renderShortcode']);
        add_filter('woocommerce_add_to_cart_fragments', [$this, 'updateFragments']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue'], 20);
    }

    public function enqueue(): void
    {
        if (is_admin()) {
            return;
        }

        wp_enqueue_style(
            self::STYLE_HANDLE,
            AM_TOOLKIT_URL . 'assets/css/cart.css',
            [],
            $this->assetVersion('assets/css/cart.css')
        );

        wp_enqueue_script(
            self::SCRIPT_HANDLE,
            AM_TOOLKIT_URL . 'assets/js/cart.js',
            ['jquery', 'wc-cart-fragments'],
            $this->assetVersion('assets/js/cart.js'),
            true
        );
    }

    public function renderShortcode(): string
    {
        if (!function_exists('WC') || !WC()->cart) {
            return '';
        }

        $count = WC()->cart->get_cart_contents_count();
        $label = sprintf(
            /* translators: %d: number of products in the cart. */
            _n('Koszyk: %d produkt', 'Koszyk: %d produktów', $count, 'am-toolkit'),
            $count
        );

        ob_start();
        ?>
        <a class="my-header-cart" href="<?php echo esc_url(wc_get_cart_url()); ?>" aria-label="<?php echo esc_attr($label); ?>">
            <?php echo $this->renderTotal(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            <span class="my-cart-icon">
                <?php echo $this->renderCount(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                <svg viewBox="0 0 1000 1000" width="22" height="22" aria-hidden="true" focusable="false">
                    <path d="M188 167H938C943 167 949 169 953 174 957 178 959 184 958 190L926 450C919 502 875 542 823 542H263L271 583C281 631 324 667 373 667H854C866 667 875 676 875 687S866 708 854 708H373C304 708 244 659 230 591L129 83H21C9 83 0 74 0 62S9 42 21 42H146C156 42 164 49 166 58L188 167ZM771 750C828 750 875 797 875 854S828 958 771 958 667 912 667 854 713 750 771 750ZM354 750C412 750 458 797 458 854S412 958 354 958 250 912 250 854 297 750 354 750Z"/>
                </svg>
            </span>
        </a>
        <?php

        return (string) ob_get_clean();
    }

    public function updateFragments(array $fragments): array
    {
        if (!function_exists('WC') || !WC()->cart) {
            return $fragments;
        }

        // Replace the complete link so its accessible label stays in sync too.
        $fragments['.my-header-cart'] = $this->renderShortcode();

        return $fragments;
    }

    private function renderCount(): string
    {
        $count = function_exists('WC') && WC()->cart
            ? WC()->cart->get_cart_contents_count()
            : 0;

        return sprintf(
            '<span class="my-cart-count" aria-hidden="true">%s</span>',
            esc_html((string) $count)
        );
    }

    private function renderTotal(): string
    {
        $total = function_exists('WC') && WC()->cart
            ? WC()->cart->get_cart_total()
            : '';

        return sprintf(
            '<span class="my-cart-total">%s</span>',
            wp_kses_post($total)
        );
    }

    private function assetVersion(string $relativePath): string
    {
        $absolutePath = AM_TOOLKIT_PATH . $relativePath;

        return file_exists($absolutePath)
            ? (string) filemtime($absolutePath)
            : AM_TOOLKIT_VERSION;
    }
}
