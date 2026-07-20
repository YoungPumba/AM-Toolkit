<?php

namespace AMToolkit\Modules\Account;

use WP_User;

defined('ABSPATH') || exit;

final class AccountOnboarding
{
    private const PENDING_META = '_amt_account_onboarding_pending';

    private bool $rendered = false;

    public function boot(): void
    {
        add_action('woocommerce_customer_reset_password', [$this, 'markNewAccountOnboarding'], 10, 1);
        add_action('template_redirect', [$this, 'handleAccountFlow'], 20);
        add_action('wp_enqueue_scripts', [$this, 'enqueue']);
        add_action('wp_body_open', [$this, 'render']);
        add_action('wp_footer', [$this, 'render'], 1);
    }

    public function markNewAccountOnboarding(WP_User $user): void
    {
        $context = sanitize_key($this->postString('amt_reset_context'));

        if ($context === 'newaccount') {
            update_user_meta($user->ID, self::PENDING_META, 'yes');
        }
    }

    public function handleAccountFlow(): void
    {
        if (!$this->isAccountPage()) {
            return;
        }

        if (isset($_POST['amt_account_onboarding_submit'])) {
            $this->saveOnboarding();
            return;
        }

        if (!$this->needsOnboarding()) {
            return;
        }

        if ($this->isAccountEndpoint()) {
            return;
        }

        if (!isset($_GET['amt-onboarding'])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            wp_safe_redirect(add_query_arg('amt-onboarding', '1', wc_get_page_permalink('myaccount')));
            exit;
        }
    }

    public function enqueue(): void
    {
        $this->enqueueCompletionToast();

        if (!$this->shouldRender()) {
            return;
        }

        wp_enqueue_style(
            'am-toolkit-account-onboarding',
            AM_TOOLKIT_URL . 'assets/css/account-onboarding.css',
            ['am-toolkit-core'],
            $this->assetVersion('assets/css/account-onboarding.css')
        );

        wp_enqueue_script(
            'am-toolkit-account-onboarding',
            AM_TOOLKIT_URL . 'assets/js/account-onboarding.js',
            [],
            $this->assetVersion('assets/js/account-onboarding.js'),
            true
        );
    }

    public function render(): void
    {
        if ($this->rendered || !$this->shouldRender()) {
            return;
        }

        $this->rendered = true;

        if ($this->isLostPasswordEndpoint()) {
            $this->renderPasswordFlow();
            return;
        }

        $this->renderOnboardingForm();
    }

    private function renderPasswordFlow(): void
    {
        $state = $this->passwordResetState();
        $isNewAccount = $state['context'] === 'newaccount';
        ?>
        <div class="amt-account-flow" data-amt-account-flow>
            <section class="amt-account-flow__card" role="dialog" aria-modal="true" aria-labelledby="amt-password-title">
                <?php $this->renderNotices(); ?>

                <?php if ($state['type'] === 'reset') : ?>
                    <span class="amt-account-flow__eyebrow">
                        <?php echo esc_html($isNewAccount ? __('Aktywacja konta', 'am-toolkit') : __('Bezpieczeństwo konta', 'am-toolkit')); ?>
                    </span>
                    <h1 id="amt-password-title" class="amt-account-flow__title">
                        <?php echo esc_html__('Ustaw swoje hasło', 'am-toolkit'); ?>
                    </h1>
                    <p class="amt-account-flow__intro">
                        <?php echo esc_html($isNewAccount ? __('To pierwszy krok konfiguracji konta. Wpisz nowe hasło, którego będziesz używać podczas logowania.', 'am-toolkit') : __('Wpisz nowe hasło, którego będziesz używać podczas kolejnych logowań.', 'am-toolkit')); ?>
                    </p>

                    <form method="post" class="amt-account-flow__form" novalidate>
                        <div class="amt-account-flow__field">
                            <label for="amt-password-1"><?php echo esc_html__('Nowe hasło', 'am-toolkit'); ?></label>
                            <div class="amt-account-flow__password-wrap">
                                <input id="amt-password-1" name="password_1" type="password" autocomplete="new-password" required data-amt-password-primary>
                                <button class="amt-account-flow__reveal" type="button" data-amt-password-toggle aria-label="<?php echo esc_attr__('Pokaż hasło', 'am-toolkit'); ?>">
                                    <?php echo esc_html__('Pokaż', 'am-toolkit'); ?>
                                </button>
                            </div>
                            <div class="amt-password-strength" data-amt-password-strength aria-live="polite">
                                <span class="amt-password-strength__bar"></span>
                                <span class="amt-password-strength__label"><?php echo esc_html__('Siła hasła', 'am-toolkit'); ?></span>
                            </div>
                        </div>

                        <div class="amt-account-flow__field">
                            <label for="amt-password-2"><?php echo esc_html__('Powtórz hasło', 'am-toolkit'); ?></label>
                            <div class="amt-account-flow__password-wrap">
                                <input id="amt-password-2" name="password_2" type="password" autocomplete="new-password" required>
                                <button class="amt-account-flow__reveal" type="button" data-amt-password-toggle aria-label="<?php echo esc_attr__('Pokaż hasło', 'am-toolkit'); ?>">
                                    <?php echo esc_html__('Pokaż', 'am-toolkit'); ?>
                                </button>
                            </div>
                        </div>

                        <input type="hidden" name="reset_key" value="<?php echo esc_attr($state['key']); ?>">
                        <input type="hidden" name="reset_login" value="<?php echo esc_attr($state['login']); ?>">
                        <input type="hidden" name="wc_reset_password" value="true">
                        <input type="hidden" name="amt_reset_context" value="<?php echo esc_attr($state['context']); ?>">
                        <?php wp_nonce_field('reset_password', 'woocommerce-reset-password-nonce'); ?>

                        <button class="amt-account-flow__submit" type="submit">
                            <?php echo esc_html($isNewAccount ? __('Ustaw hasło i przejdź dalej', 'am-toolkit') : __('Zapisz nowe hasło', 'am-toolkit')); ?>
                        </button>
                    </form>

                <?php elseif ($state['type'] === 'sent') : ?>
                    <span class="amt-account-flow__eyebrow"><?php echo esc_html__('Sprawdź skrzynkę', 'am-toolkit'); ?></span>
                    <h1 id="amt-password-title" class="amt-account-flow__title">
                        <?php echo esc_html__('Wiadomość została wysłana', 'am-toolkit'); ?>
                    </h1>
                    <p class="amt-account-flow__intro">
                        <?php echo esc_html__('Jeżeli podany adres należy do konta, otrzymasz wiadomość z bezpiecznym odnośnikiem do ustawienia hasła.', 'am-toolkit'); ?>
                    </p>
                    <a class="amt-account-flow__submit" href="<?php echo esc_url(wc_get_page_permalink('myaccount')); ?>">
                        <?php echo esc_html__('Wróć do logowania', 'am-toolkit'); ?>
                    </a>

                <?php else : ?>
                    <span class="amt-account-flow__eyebrow"><?php echo esc_html__('Pomoc z logowaniem', 'am-toolkit'); ?></span>
                    <h1 id="amt-password-title" class="amt-account-flow__title">
                        <?php echo esc_html($state['type'] === 'invalid' ? __('Odnośnik wygasł lub został wykorzystany', 'am-toolkit') : __('Ustaw nowe hasło', 'am-toolkit')); ?>
                    </h1>
                    <p class="amt-account-flow__intro">
                        <?php echo esc_html($state['type'] === 'invalid' ? __('Podaj adres e-mail konta, a wyślemy Ci nowy odnośnik.', 'am-toolkit') : __('Podaj adres e-mail użyty podczas rejestracji.', 'am-toolkit')); ?>
                    </p>

                    <form method="post" class="amt-account-flow__form">
                        <div class="amt-account-flow__field">
                            <label for="amt-user-login"><?php echo esc_html__('Adres e-mail', 'am-toolkit'); ?></label>
                            <input id="amt-user-login" name="user_login" type="email" autocomplete="email" required>
                        </div>
                        <input type="hidden" name="wc_reset_password" value="true">
                        <?php wp_nonce_field('lost_password', 'woocommerce-lost-password-nonce'); ?>
                        <button class="amt-account-flow__submit" type="submit">
                            <?php echo esc_html__('Wyślij nowy odnośnik', 'am-toolkit'); ?>
                        </button>
                    </form>
                <?php endif; ?>
            </section>
        </div>
        <?php
    }

    private function renderOnboardingForm(): void
    {
        $user = wp_get_current_user();
        $firstName = $this->postedValue('account_first_name', (string) $user->first_name);
        $lastName = $this->postedValue('account_last_name', (string) $user->last_name);
        $displayName = $this->postedValue('account_display_name', (string) $user->display_name);
        $phone = $this->postedValue('billing_phone', (string) get_user_meta($user->ID, 'billing_phone', true));
        ?>
        <div class="amt-account-flow" data-amt-account-flow>
            <section class="amt-account-flow__card amt-account-flow__card--wide" role="dialog" aria-modal="true" aria-labelledby="amt-onboarding-title">
                <?php $this->renderNotices(); ?>

                <span class="amt-account-flow__eyebrow"><?php echo esc_html__('Ostatni krok', 'am-toolkit'); ?></span>
                <h1 id="amt-onboarding-title" class="amt-account-flow__title">
                    <?php echo esc_html__('Uzupełnij swoje konto', 'am-toolkit'); ?>
                </h1>
                <p class="amt-account-flow__intro">
                    <?php echo esc_html__('Dzięki tym informacjom łatwiej rozpoznasz swoje konto i szybciej przejdziesz przez kolejne zakupy.', 'am-toolkit'); ?>
                </p>

                <form method="post" class="amt-account-flow__form">
                    <div class="amt-account-flow__grid">
                        <div class="amt-account-flow__field">
                            <label for="amt-first-name"><?php echo esc_html__('Imię', 'am-toolkit'); ?> <span aria-hidden="true">*</span></label>
                            <input id="amt-first-name" name="account_first_name" type="text" autocomplete="given-name" value="<?php echo esc_attr($firstName); ?>" required>
                        </div>

                        <div class="amt-account-flow__field">
                            <label for="amt-last-name"><?php echo esc_html__('Nazwisko', 'am-toolkit'); ?> <span aria-hidden="true">*</span></label>
                            <input id="amt-last-name" name="account_last_name" type="text" autocomplete="family-name" value="<?php echo esc_attr($lastName); ?>" required>
                        </div>
                    </div>

                    <div class="amt-account-flow__field">
                        <label for="amt-display-name"><?php echo esc_html__('Nazwa wyświetlana', 'am-toolkit'); ?> <span aria-hidden="true">*</span></label>
                        <input id="amt-display-name" name="account_display_name" type="text" autocomplete="nickname" value="<?php echo esc_attr($displayName); ?>" required>
                        <small><?php echo esc_html__('Tak będziemy zwracać się do Ciebie w panelu konta.', 'am-toolkit'); ?></small>
                    </div>

                    <div class="amt-account-flow__field">
                        <label for="amt-phone"><?php echo esc_html__('Numer telefonu', 'am-toolkit'); ?> <span class="amt-account-flow__optional"><?php echo esc_html__('opcjonalnie', 'am-toolkit'); ?></span></label>
                        <input id="amt-phone" name="billing_phone" type="tel" autocomplete="tel" value="<?php echo esc_attr($phone); ?>">
                    </div>

                    <div class="amt-account-flow__email">
                        <span><?php echo esc_html__('Adres e-mail konta', 'am-toolkit'); ?></span>
                        <strong><?php echo esc_html($user->user_email); ?></strong>
                    </div>

                    <input type="hidden" name="amt_account_onboarding_submit" value="1">
                    <?php wp_nonce_field('amt_account_onboarding', 'amt_account_onboarding_nonce'); ?>

                    <button class="amt-account-flow__submit" type="submit">
                        <?php echo esc_html__('Zapisz i przejdź do panelu', 'am-toolkit'); ?>
                    </button>
                </form>
            </section>
        </div>
        <?php
    }

    /**
     * @return array{type: string, key: string, login: string, context: string}
     */
    private function passwordResetState(): array
    {
        if (isset($_GET['reset-link-sent'])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            return ['type' => 'sent', 'key' => '', 'login' => '', 'context' => ''];
        }

        if (!isset($_GET['show-reset-form'])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            return ['type' => 'request', 'key' => '', 'login' => '', 'context' => ''];
        }

        $cookieName = 'wp-resetpass-' . COOKIEHASH;
        $cookie = isset($_COOKIE[$cookieName]) && is_string($_COOKIE[$cookieName])
            ? wc_clean(wp_unslash($_COOKIE[$cookieName]))
            : '';

        if ($cookie === '' || strpos($cookie, ':') === false) {
            return ['type' => 'invalid', 'key' => '', 'login' => '', 'context' => ''];
        }

        [$userId, $key] = explode(':', $cookie, 2);
        $user = get_userdata(absint($userId));
        $login = $user ? $user->user_login : '';
        $validatedUser = $login !== '' ? check_password_reset_key($key, $login) : false;

        if (!($validatedUser instanceof WP_User)) {
            return ['type' => 'invalid', 'key' => '', 'login' => '', 'context' => ''];
        }

        $context = isset($_GET['action']) && is_string($_GET['action']) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            ? sanitize_key(wp_unslash($_GET['action'])) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            : '';

        return [
            'type'    => 'reset',
            'key'     => $key,
            'login'   => $login,
            'context' => $context,
        ];
    }

    private function saveOnboarding(): void
    {
        if (!is_user_logged_in()) {
            return;
        }

        $nonce = sanitize_text_field($this->postString('amt_account_onboarding_nonce'));

        if (!wp_verify_nonce($nonce, 'amt_account_onboarding')) {
            wc_add_notice(__('Sesja formularza wygasła. Spróbuj ponownie.', 'am-toolkit'), 'error');
            return;
        }

        $firstName = wc_clean($this->postString('account_first_name'));
        $lastName = wc_clean($this->postString('account_last_name'));
        $displayName = wc_clean($this->postString('account_display_name'));
        $phone = wc_sanitize_phone_number($this->postString('billing_phone'));

        if ($firstName === '') {
            wc_add_notice(__('Podaj imię.', 'am-toolkit'), 'error');
        }

        if ($lastName === '') {
            wc_add_notice(__('Podaj nazwisko.', 'am-toolkit'), 'error');
        }

        if ($displayName === '') {
            wc_add_notice(__('Podaj nazwę wyświetlaną.', 'am-toolkit'), 'error');
        }

        if (wc_notice_count('error') > 0) {
            return;
        }

        $userId = get_current_user_id();
        $result = wp_update_user([
            'ID'           => $userId,
            'first_name'   => $firstName,
            'last_name'    => $lastName,
            'display_name' => $displayName,
        ]);

        if (is_wp_error($result)) {
            wc_add_notice($result->get_error_message(), 'error');
            return;
        }

        update_user_meta($userId, 'billing_first_name', $firstName);
        update_user_meta($userId, 'billing_last_name', $lastName);
        update_user_meta($userId, 'billing_phone', $phone);
        delete_user_meta($userId, self::PENDING_META);

        wc_nocache_headers();
        wp_safe_redirect(add_query_arg('amt-onboarding-complete', '1', wc_get_page_permalink('myaccount')));
        exit;
    }

    private function renderNotices(): void
    {
        if (function_exists('wc_print_notices')) {
            wc_print_notices();
        }
    }

    private function postedValue(string $key, string $fallback): string
    {
        if (!isset($_POST['amt_account_onboarding_submit'], $_POST[$key]) || !is_string($_POST[$key])) {
            return $fallback;
        }

        return wc_clean(wp_unslash($_POST[$key]));
    }

    private function postString(string $key): string
    {
        if (!isset($_POST[$key]) || !is_string($_POST[$key])) {
            return '';
        }

        return wp_unslash($_POST[$key]);
    }

    private function enqueueCompletionToast(): void
    {
        if (
            !$this->isAccountPage() ||
            !is_user_logged_in() ||
            !isset($_GET['amt-onboarding-complete']) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        ) {
            return;
        }

        $payload = wp_json_encode([
            'type'    => 'success',
            'title'   => __('Konto jest gotowe', 'am-toolkit'),
            'message' => __('Twoje dane zostały zapisane. Witamy w panelu konta!', 'am-toolkit'),
        ]);

        wp_add_inline_script(
            'am-toolkit-toast',
            "(function(){if(window.AMTAccountCompletionToastBound){return;}window.AMTAccountCompletionToastBound=true;window.addEventListener('DOMContentLoaded',function(){var show=function(){if(window.AMTAccountCompletionToastShown){return;}window.AMTAccountCompletionToastShown=true;if(window.history&&window.history.replaceState){var url=new URL(window.location.href);url.searchParams.delete('amt-onboarding-complete');window.history.replaceState({},'',url.pathname+url.search+url.hash);}if(window.AMToolkit&&typeof window.AMToolkit.toast==='function'){window.AMToolkit.toast({$payload});}};if(document.querySelector('[data-am-account-welcome]')){window.setTimeout(show,6500);}else{show();}});}());",
            'after'
        );
    }

    private function shouldRender(): bool
    {
        if (!$this->isAccountPage() || isset($_GET['elementor-preview'])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            return false;
        }

        return $this->isLostPasswordEndpoint() || $this->needsOnboarding();
    }

    private function needsOnboarding(): bool
    {
        return is_user_logged_in() && get_user_meta(get_current_user_id(), self::PENDING_META, true) === 'yes';
    }

    private function isAccountPage(): bool
    {
        return function_exists('is_account_page') && is_account_page();
    }

    private function isAccountEndpoint(): bool
    {
        return function_exists('is_wc_endpoint_url') && is_wc_endpoint_url();
    }

    private function isLostPasswordEndpoint(): bool
    {
        return function_exists('is_wc_endpoint_url') && is_wc_endpoint_url('lost-password');
    }

    private function assetVersion(string $relativePath): string
    {
        $absolutePath = AM_TOOLKIT_PATH . $relativePath;

        return file_exists($absolutePath)
            ? (string) filemtime($absolutePath)
            : AM_TOOLKIT_VERSION;
    }
}
