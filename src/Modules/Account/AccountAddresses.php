<?php

namespace AMToolkit\Modules\Account;

defined('ABSPATH') || exit;

final class AccountAddresses
{
    private const ENDPOINT = 'edit-address';

    public function boot(): void
    {
        add_shortcode('am_account_addresses', [$this, 'render']);
        add_filter('template_include', [$this, 'accountTemplate'], 103);
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

        $pluginTemplate = AM_TOOLKIT_PATH . 'templates/account/account-addresses.php';

        return file_exists($pluginTemplate) ? $pluginTemplate : $template;
    }

    public function render(): string
    {
        if (
            !is_user_logged_in() ||
            !function_exists('WC') ||
            !class_exists('\WC_Customer')
        ) {
            return '';
        }

        $customer = new \WC_Customer(get_current_user_id());

        if ($customer->get_id() <= 0) {
            return '';
        }

        ob_start();
        ?>
        <section class="am-account-addresses" aria-labelledby="am-account-addresses-title">
            <?php if (function_exists('wc_print_notices')) : ?>
                <div class="am-account-addresses__notices" aria-live="polite">
                    <?php wc_print_notices(); ?>
                </div>
            <?php endif; ?>

            <header class="am-account-addresses__header">
                <div>
                    <span class="am-account-addresses__eyebrow">
                        <?php echo esc_html__('Dane do zamówień', 'am-toolkit'); ?>
                    </span>
                    <h1 id="am-account-addresses-title" class="am-account-addresses__title">
                        <?php echo esc_html__('Moje adresy', 'am-toolkit'); ?>
                    </h1>
                    <p class="am-account-addresses__intro">
                        <?php
                        echo esc_html__(
                            'Zapisane dane przyspieszą kolejne zakupy. Każdy adres możesz aktualizować niezależnie.',
                            'am-toolkit'
                        );
                        ?>
                    </p>
                </div>

                <span class="am-account-addresses__header-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" focusable="false">
                        <path d="M12 21s7-5.2 7-12a7 7 0 1 0-14 0c0 6.8 7 12 7 12Z" />
                        <circle cx="12" cy="9" r="2.5" />
                    </svg>
                </span>
            </header>

            <div class="am-account-addresses__grid">
                <?php
                echo $this->addressForm(
                    'billing',
                    __('Adres rozliczeniowy', 'am-toolkit'),
                    __('Używany na fakturach, rachunkach i podczas płatności.', 'am-toolkit'),
                    $customer
                ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

                echo $this->addressForm(
                    'shipping',
                    __('Adres dostawy', 'am-toolkit'),
                    __('Używany przy zamówieniach wymagających fizycznej wysyłki.', 'am-toolkit'),
                    $customer
                ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                ?>
            </div>
        </section>
        <?php

        return (string) ob_get_clean();
    }

    private function addressForm(
        string $type,
        string $title,
        string $description,
        \WC_Customer $customer
    ): string {
        if (!in_array($type, ['billing', 'shipping'], true)) {
            return '';
        }

        $countryGetter = 'get_' . $type . '_country';
        $country = is_callable([$customer, $countryGetter])
            ? (string) $customer->{$countryGetter}('edit')
            : '';

        if ($country === '' && WC()->countries) {
            $country = (string) WC()->countries->get_base_country();
        }

        $fields = WC()->countries
            ? WC()->countries->get_address_fields($country, $type . '_')
            : [];

        if ($fields === []) {
            return '';
        }

        $formAction = wc_get_endpoint_url(
            self::ENDPOINT,
            $type,
            wc_get_page_permalink('myaccount')
        );

        ob_start();
        ?>
        <section
            class="am-account-addresses__card am-account-addresses__card--<?php echo esc_attr($type); ?>"
            aria-labelledby="am-account-<?php echo esc_attr($type); ?>-address-title"
        >
            <header class="am-account-addresses__card-header">
                <span class="am-account-addresses__card-icon" aria-hidden="true">
                    <?php echo $this->addressIcon($type); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                </span>
                <div>
                    <span>
                        <?php echo esc_html('billing' === $type ? __('Rozliczenia', 'am-toolkit') : __('Dostawa', 'am-toolkit')); ?>
                    </span>
                    <h2 id="am-account-<?php echo esc_attr($type); ?>-address-title">
                        <?php echo esc_html($title); ?>
                    </h2>
                </div>
            </header>

            <p class="am-account-addresses__card-description">
                <?php echo esc_html($description); ?>
            </p>

            <?php do_action('woocommerce_before_edit_address_form_' . $type); ?>

            <form
                class="am-account-addresses__form woocommerce-address-fields"
                action="<?php echo esc_url($formAction); ?>"
                method="post"
            >
                <div class="am-account-addresses__fields woocommerce-address-fields__field-wrapper">
                    <?php foreach ($fields as $key => $field) : ?>
                        <?php
                        woocommerce_form_field(
                            $key,
                            $field,
                            $this->fieldValue($customer, $key)
                        );
                        ?>
                    <?php endforeach; ?>
                </div>

                <?php do_action('woocommerce_after_edit_address_form_' . $type); ?>

                <footer class="am-account-addresses__footer">
                    <p><?php echo esc_html__('Pola oznaczone gwiazdką są wymagane.', 'am-toolkit'); ?></p>
                    <?php wp_nonce_field('woocommerce-edit_address', 'woocommerce-edit-address-nonce'); ?>
                    <button
                        type="submit"
                        class="am-account-addresses__submit button"
                        name="save_address"
                        value="<?php echo esc_attr__('Zapisz adres', 'am-toolkit'); ?>"
                    >
                        <?php
                        echo esc_html(
                            'billing' === $type
                                ? __('Zapisz adres rozliczeniowy', 'am-toolkit')
                                : __('Zapisz adres dostawy', 'am-toolkit')
                        );
                        ?>
                    </button>
                    <input type="hidden" name="action" value="edit_address" />
                </footer>
            </form>
        </section>
        <?php

        return (string) ob_get_clean();
    }

    private function fieldValue(\WC_Customer $customer, string $key): string
    {
        if (isset($_POST[$key])) {
            $postedValue = wp_unslash($_POST[$key]);

            return is_scalar($postedValue)
                ? (string) wc_clean($postedValue)
                : '';
        }

        $getter = 'get_' . $key;

        if (is_callable([$customer, $getter])) {
            return (string) $customer->{$getter}('edit');
        }

        return (string) get_user_meta($customer->get_id(), $key, true);
    }

    private function addressIcon(string $type): string
    {
        if ('shipping' === $type) {
            return '<svg viewBox="0 0 24 24" focusable="false"><path d="M3 6h11v11H3zM14 10h4l3 3v4h-7zM7 20a2 2 0 1 0 0-4 2 2 0 0 0 0 4ZM18 20a2 2 0 1 0 0-4 2 2 0 0 0 0 4Z"/></svg>';
        }

        return '<svg viewBox="0 0 24 24" focusable="false"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 9h18M7 15h4"/></svg>';
    }
}
