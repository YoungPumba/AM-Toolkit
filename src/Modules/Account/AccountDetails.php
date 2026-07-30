<?php

namespace AMToolkit\Modules\Account;

defined('ABSPATH') || exit;

final class AccountDetails
{
    private const ENDPOINT = 'edit-account';
    private const SCRIPT_HANDLE = 'am-toolkit-account-details';
    private const AVATAR_META_KEY = '_am_toolkit_avatar_attachment_id';
    private const AVATAR_OWNER_META_KEY = '_am_toolkit_avatar_owner';
    private const MAX_AVATAR_SIZE = 3145728;

    public function boot(): void
    {
        add_shortcode('am_account_details', [$this, 'render']);
        add_filter('template_include', [$this, 'accountTemplate'], 102);
        add_action('woocommerce_save_account_details', [$this, 'saveOptionalPhone']);
        add_action('woocommerce_save_account_details', [$this, 'saveAvatar'], 20);
        add_action('wp_enqueue_scripts', [$this, 'enqueueAssets']);
        add_filter('get_avatar_data', [$this, 'filterAvatarData'], 10, 2);
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

        $pluginTemplate = AM_TOOLKIT_PATH . 'templates/account/account-details.php';

        return file_exists($pluginTemplate) ? $pluginTemplate : $template;
    }

    public function enqueueAssets(): void
    {
        if (
            !is_user_logged_in() ||
            !function_exists('is_wc_endpoint_url') ||
            !is_wc_endpoint_url(self::ENDPOINT)
        ) {
            return;
        }

        $relativePath = 'assets/js/account-details.js';
        $absolutePath = AM_TOOLKIT_PATH . $relativePath;

        wp_enqueue_script(
            self::SCRIPT_HANDLE,
            AM_TOOLKIT_URL . $relativePath,
            [],
            file_exists($absolutePath) ? (string) filemtime($absolutePath) : AM_TOOLKIT_VERSION,
            true
        );
    }

    public function saveOptionalPhone(int $userId): void
    {
        $nonce = isset($_POST['save-account-details-nonce'])
            ? sanitize_text_field(wp_unslash($_POST['save-account-details-nonce']))
            : '';

        if (
            $userId <= 0 ||
            $userId !== get_current_user_id() ||
            !wp_verify_nonce($nonce, 'save_account_details')
        ) {
            return;
        }

        $phone = isset($_POST['billing_phone'])
            ? wp_unslash($_POST['billing_phone'])
            : '';
        $phone = function_exists('wc_sanitize_phone')
            ? wc_sanitize_phone($phone)
            : sanitize_text_field($phone);

        update_user_meta($userId, 'billing_phone', $phone);
    }

    public function saveAvatar(int $userId): void
    {
        $nonce = isset($_POST['save-account-details-nonce'])
            ? sanitize_text_field(wp_unslash($_POST['save-account-details-nonce']))
            : '';

        if (
            $userId <= 0 ||
            $userId !== get_current_user_id() ||
            !wp_verify_nonce($nonce, 'save_account_details')
        ) {
            return;
        }

        $file = isset($_FILES['am_account_avatar']) && is_array($_FILES['am_account_avatar'])
            ? $_FILES['am_account_avatar']
            : [];
        $hasUpload = isset($file['error']) && (int) $file['error'] !== UPLOAD_ERR_NO_FILE;
        $removeAvatar = isset($_POST['am_remove_account_avatar'])
            && '1' === sanitize_text_field(wp_unslash($_POST['am_remove_account_avatar']));

        if ($removeAvatar && !$hasUpload) {
            $this->removeCustomAvatar($userId);
            return;
        }

        if (!$hasUpload) {
            return;
        }

        $uploadError = isset($file['error']) ? (int) $file['error'] : UPLOAD_ERR_NO_FILE;

        if ($uploadError !== UPLOAD_ERR_OK) {
            wc_add_notice(
                __('Nie udało się przesłać avatara. Wybierz plik ponownie.', 'am-toolkit'),
                'error'
            );
            return;
        }

        $fileSize = isset($file['size']) ? (int) $file['size'] : 0;

        if ($fileSize <= 0 || $fileSize > self::MAX_AVATAR_SIZE) {
            wc_add_notice(
                __('Avatar może mieć maksymalnie 3 MB.', 'am-toolkit'),
                'error'
            );
            return;
        }

        $fileName = isset($file['name']) ? sanitize_file_name((string) $file['name']) : '';
        $temporaryPath = isset($file['tmp_name']) ? (string) $file['tmp_name'] : '';
        $allowedMimes = [
            'jpg|jpeg|jpe' => 'image/jpeg',
            'png'          => 'image/png',
            'webp'         => 'image/webp',
        ];
        $checkedFile = wp_check_filetype_and_ext(
            $temporaryPath,
            $fileName,
            $allowedMimes
        );

        if (empty($checkedFile['ext']) || empty($checkedFile['type'])) {
            wc_add_notice(
                __('Avatar musi być obrazem JPG, PNG albo WebP.', 'am-toolkit'),
                'error'
            );
            return;
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';

        $uploaded = wp_handle_upload(
            $file,
            [
                'test_form' => false,
                'mimes'     => $allowedMimes,
            ]
        );

        if (isset($uploaded['error']) || empty($uploaded['file']) || empty($uploaded['url'])) {
            wc_add_notice(
                isset($uploaded['error'])
                    ? sanitize_text_field((string) $uploaded['error'])
                    : __('Nie udało się zapisać avatara.', 'am-toolkit'),
                'error'
            );
            return;
        }

        $attachmentId = wp_insert_attachment(
            [
                'post_mime_type' => sanitize_mime_type((string) ($uploaded['type'] ?? $checkedFile['type'])),
                'post_title'     => sanitize_text_field(pathinfo($fileName, PATHINFO_FILENAME)),
                'post_content'   => '',
                'post_status'    => 'inherit',
                'post_author'    => $userId,
            ],
            (string) $uploaded['file']
        );

        if (is_wp_error($attachmentId) || $attachmentId <= 0) {
            wp_delete_file((string) $uploaded['file']);
            wc_add_notice(
                __('Nie udało się utworzyć avatara. Spróbuj ponownie.', 'am-toolkit'),
                'error'
            );
            return;
        }

        $metadata = wp_generate_attachment_metadata(
            $attachmentId,
            (string) $uploaded['file']
        );

        if (is_array($metadata)) {
            wp_update_attachment_metadata($attachmentId, $metadata);
        }

        $previousAttachmentId = (int) get_user_meta(
            $userId,
            self::AVATAR_META_KEY,
            true
        );

        update_post_meta($attachmentId, self::AVATAR_OWNER_META_KEY, $userId);
        update_user_meta($userId, self::AVATAR_META_KEY, $attachmentId);

        if ($previousAttachmentId > 0 && $previousAttachmentId !== $attachmentId) {
            $this->deleteOwnedAvatarAttachment($previousAttachmentId, $userId);
        }
    }

    /**
     * @param array<string, mixed> $args
     * @param mixed $idOrEmail
     * @return array<string, mixed>
     */
    public function filterAvatarData(array $args, $idOrEmail): array
    {
        $userId = $this->resolveAvatarUserId($idOrEmail);

        if ($userId <= 0) {
            return $args;
        }

        $attachmentId = (int) get_user_meta(
            $userId,
            self::AVATAR_META_KEY,
            true
        );

        if ($attachmentId <= 0 || !wp_attachment_is_image($attachmentId)) {
            return $args;
        }

        $size = isset($args['size']) ? max(24, (int) $args['size']) : 96;
        $image = wp_get_attachment_image_src($attachmentId, [$size, $size]);

        if (!is_array($image) || empty($image[0])) {
            return $args;
        }

        $args['url'] = $image[0];
        $args['found_avatar'] = true;

        return $args;
    }

    public function render(): string
    {
        if (!is_user_logged_in()) {
            return '';
        }

        $user = get_user_by('id', get_current_user_id());

        if (!$user instanceof \WP_User) {
            return '';
        }

        $firstName   = $this->fieldValue('account_first_name', $user->first_name);
        $lastName    = $this->fieldValue('account_last_name', $user->last_name);
        $displayName = $this->fieldValue('account_display_name', $user->display_name);
        $email       = $this->fieldValue('account_email', $user->user_email);
        $phone       = $this->fieldValue('billing_phone', (string) get_user_meta($user->ID, 'billing_phone', true));
        $customAvatarId = (int) get_user_meta($user->ID, self::AVATAR_META_KEY, true);

        ob_start();
        ?>
        <section class="am-account-details" data-am-account-details aria-labelledby="am-account-details-title">
            <?php if (function_exists('wc_print_notices')) : ?>
                <div class="am-account-details__notices" aria-live="polite">
                    <?php wc_print_notices(); ?>
                </div>
            <?php endif; ?>

            <header class="am-account-details__header">
                <div class="am-account-details__heading">
                    <span class="am-account-details__eyebrow"><?php echo esc_html__('Ustawienia profilu', 'am-toolkit'); ?></span>
                    <h1 id="am-account-details-title" class="am-account-details__title">
                        <?php echo esc_html__('Dane konta', 'am-toolkit'); ?>
                    </h1>
                    <p class="am-account-details__intro">
                        <?php echo esc_html__('Zadbaj o aktualne dane kontaktowe i bezpieczeństwo swojego konta.', 'am-toolkit'); ?>
                    </p>
                </div>

                <div class="am-account-details__profile">
                    <div class="am-account-details__profile-text">
                        <strong><?php echo esc_html($user->display_name); ?></strong>
                        <span><?php echo esc_html($user->user_email); ?></span>
                    </div>
                    <?php
                    echo get_avatar(
                        $user->ID,
                        88,
                        '',
                        sprintf(__('Avatar użytkownika %s', 'am-toolkit'), $user->display_name),
                        ['class' => 'am-account-details__avatar']
                    ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                    ?>
                </div>
            </header>

            <?php do_action('woocommerce_before_edit_account_form'); ?>

            <form
                class="am-account-details__form woocommerce-EditAccountForm edit-account"
                action=""
                method="post"
                enctype="multipart/form-data"
                <?php do_action('woocommerce_edit_account_form_tag'); ?>
            >
                <?php do_action('woocommerce_edit_account_form_start'); ?>

                <section class="am-account-details__section" aria-labelledby="am-account-personal-title">
                    <header class="am-account-details__section-header">
                        <span class="am-account-details__section-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" focusable="false">
                                <path d="M12 12a4.5 4.5 0 1 0 0-9 4.5 4.5 0 0 0 0 9Zm8 9a8 8 0 0 0-16 0" />
                            </svg>
                        </span>
                        <div>
                            <span><?php echo esc_html__('Twój profil', 'am-toolkit'); ?></span>
                            <h2 id="am-account-personal-title"><?php echo esc_html__('Podstawowe informacje', 'am-toolkit'); ?></h2>
                        </div>
                    </header>

                    <div class="am-account-details__fields">
                        <div class="am-account-details__avatar-editor am-account-details__field--wide">
                            <div class="am-account-details__avatar-preview">
                                <?php
                                echo get_avatar(
                                    $user->ID,
                                    112,
                                    '',
                                    sprintf(__('Podgląd avatara użytkownika %s', 'am-toolkit'), $user->display_name),
                                    [
                                        'class' => 'am-account-details__avatar-preview-image',
                                        'extra_attr' => 'data-am-account-avatar-preview="true"',
                                    ]
                                ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                ?>
                            </div>
                            <div class="am-account-details__avatar-controls">
                                <strong><?php echo esc_html__('Zdjęcie profilowe', 'am-toolkit'); ?></strong>
                                <p>
                                    <?php echo esc_html__('Najlepiej użyć kwadratowego zdjęcia JPG, PNG lub WebP. Maksymalny rozmiar pliku: 3 MB.', 'am-toolkit'); ?>
                                </p>
                                <div class="am-account-details__avatar-actions">
                                    <input
                                        class="am-account-details__avatar-input"
                                        type="file"
                                        name="am_account_avatar"
                                        id="am_account_avatar"
                                        accept="image/jpeg,image/png,image/webp"
                                        data-am-account-avatar-input
                                    />
                                    <label class="am-account-details__avatar-select" for="am_account_avatar">
                                        <?php echo esc_html($customAvatarId > 0 ? __('Zmień zdjęcie', 'am-toolkit') : __('Wybierz zdjęcie', 'am-toolkit')); ?>
                                    </label>
                                    <span class="am-account-details__avatar-file" data-am-account-avatar-file>
                                        <?php echo esc_html__('Nie wybrano nowego pliku', 'am-toolkit'); ?>
                                    </span>
                                </div>
                                <span
                                    class="am-account-details__avatar-status"
                                    data-am-account-avatar-status
                                    role="status"
                                    aria-live="polite"
                                    hidden
                                ></span>
                                <?php if ($customAvatarId > 0) : ?>
                                    <label class="am-account-details__avatar-remove">
                                        <input
                                            type="checkbox"
                                            name="am_remove_account_avatar"
                                            value="1"
                                            data-am-account-avatar-remove
                                        />
                                        <span><?php echo esc_html__('Usuń własny avatar i wróć do Gravatara', 'am-toolkit'); ?></span>
                                    </label>
                                <?php endif; ?>
                            </div>
                        </div>

                        <?php
                        echo $this->textField(
                            'account_first_name',
                            __('Imię', 'am-toolkit'),
                            $firstName,
                            'given-name',
                            true
                        ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                        echo $this->textField(
                            'account_last_name',
                            __('Nazwisko', 'am-toolkit'),
                            $lastName,
                            'family-name',
                            true
                        ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                        echo $this->textField(
                            'account_display_name',
                            __('Nazwa wyświetlana', 'am-toolkit'),
                            $displayName,
                            'nickname',
                            true,
                            __('Tak będziemy zwracać się do Ciebie w panelu konta.', 'am-toolkit')
                        ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                        echo $this->textField(
                            'billing_phone',
                            __('Numer telefonu', 'am-toolkit'),
                            $phone,
                            'tel',
                            false,
                            __('Pole opcjonalne. Może ułatwić kontakt w sprawie konsultacji lub zamówienia.', 'am-toolkit'),
                            'tel'
                        ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                        ?>

                        <p class="am-account-details__field am-account-details__field--wide">
                            <label for="account_email">
                                <?php echo esc_html__('Adres e-mail', 'am-toolkit'); ?>
                                <span aria-hidden="true">*</span>
                            </label>
                            <input
                                type="email"
                                class="woocommerce-Input woocommerce-Input--email input-text"
                                name="account_email"
                                id="account_email"
                                autocomplete="email"
                                value="<?php echo esc_attr($email); ?>"
                                required
                                aria-required="true"
                            />
                        </p>
                    </div>
                </section>

                <section class="am-account-details__section" aria-labelledby="am-account-password-title">
                    <header class="am-account-details__section-header">
                        <span class="am-account-details__section-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" focusable="false">
                                <rect x="5" y="10" width="14" height="11" rx="2" />
                                <path d="M8 10V7a4 4 0 0 1 8 0v3M12 14v3" />
                            </svg>
                        </span>
                        <div>
                            <span><?php echo esc_html__('Bezpieczeństwo', 'am-toolkit'); ?></span>
                            <h2 id="am-account-password-title"><?php echo esc_html__('Zmiana hasła', 'am-toolkit'); ?></h2>
                        </div>
                    </header>

                    <p class="am-account-details__password-intro">
                        <?php echo esc_html__('Jeżeli nie chcesz zmieniać hasła, pozostaw poniższe pola puste.', 'am-toolkit'); ?>
                    </p>

                    <div class="am-account-details__password-fields">
                        <?php
                        echo $this->passwordField(
                            'password_current',
                            __('Aktualne hasło', 'am-toolkit'),
                            'current-password'
                        ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                        echo $this->passwordField(
                            'password_1',
                            __('Nowe hasło', 'am-toolkit'),
                            'new-password'
                        ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                        echo $this->passwordField(
                            'password_2',
                            __('Powtórz nowe hasło', 'am-toolkit'),
                            'new-password'
                        ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                        ?>
                    </div>
                </section>

                <?php do_action('woocommerce_edit_account_form'); ?>

                <footer class="am-account-details__footer">
                    <p><?php echo esc_html__('Pola oznaczone gwiazdką są wymagane.', 'am-toolkit'); ?></p>
                    <?php wp_nonce_field('save_account_details', 'save-account-details-nonce'); ?>
                    <button
                        type="submit"
                        class="am-account-details__submit woocommerce-Button button"
                        name="save_account_details"
                        value="<?php echo esc_attr__('Zapisz zmiany', 'am-toolkit'); ?>"
                    >
                        <?php echo esc_html__('Zapisz zmiany', 'am-toolkit'); ?>
                    </button>
                    <input type="hidden" name="action" value="save_account_details" />
                </footer>

                <?php do_action('woocommerce_edit_account_form_end'); ?>
            </form>

            <?php do_action('woocommerce_after_edit_account_form'); ?>
        </section>
        <?php

        return (string) ob_get_clean();
    }

    private function fieldValue(string $key, string $fallback): string
    {
        if (!isset($_POST[$key])) {
            return $fallback;
        }

        return sanitize_text_field(wp_unslash($_POST[$key]));
    }

    private function textField(
        string $name,
        string $label,
        string $value,
        string $autocomplete,
        bool $required,
        string $description = '',
        string $type = 'text'
    ): string {
        ob_start();
        ?>
        <p class="am-account-details__field">
            <label for="<?php echo esc_attr($name); ?>">
                <?php echo esc_html($label); ?>
                <?php if ($required) : ?><span aria-hidden="true">*</span><?php endif; ?>
            </label>
            <input
                type="<?php echo esc_attr($type); ?>"
                class="woocommerce-Input woocommerce-Input--text input-text"
                name="<?php echo esc_attr($name); ?>"
                id="<?php echo esc_attr($name); ?>"
                autocomplete="<?php echo esc_attr($autocomplete); ?>"
                value="<?php echo esc_attr($value); ?>"
                <?php if ($required) : ?>required aria-required="true"<?php endif; ?>
            />
            <?php if ($description !== '') : ?>
                <small><?php echo esc_html($description); ?></small>
            <?php endif; ?>
        </p>
        <?php

        return (string) ob_get_clean();
    }

    private function passwordField(string $name, string $label, string $autocomplete): string
    {
        ob_start();
        ?>
        <p class="am-account-details__field am-account-details__password-field">
            <label for="<?php echo esc_attr($name); ?>"><?php echo esc_html($label); ?></label>
            <span class="am-account-details__password-wrap">
                <input
                    type="password"
                    class="woocommerce-Input woocommerce-Input--password input-text"
                    name="<?php echo esc_attr($name); ?>"
                    id="<?php echo esc_attr($name); ?>"
                    autocomplete="<?php echo esc_attr($autocomplete); ?>"
                />
                <button
                    type="button"
                    class="am-account-details__password-toggle"
                    data-am-account-password-toggle
                    aria-controls="<?php echo esc_attr($name); ?>"
                    aria-pressed="false"
                >
                    <?php echo esc_html__('Pokaż', 'am-toolkit'); ?>
                </button>
            </span>
        </p>
        <?php

        return (string) ob_get_clean();
    }

    private function removeCustomAvatar(int $userId): void
    {
        $attachmentId = (int) get_user_meta(
            $userId,
            self::AVATAR_META_KEY,
            true
        );

        delete_user_meta($userId, self::AVATAR_META_KEY);

        if ($attachmentId > 0) {
            $this->deleteOwnedAvatarAttachment($attachmentId, $userId);
        }
    }

    private function deleteOwnedAvatarAttachment(int $attachmentId, int $userId): void
    {
        $ownerId = (int) get_post_meta(
            $attachmentId,
            self::AVATAR_OWNER_META_KEY,
            true
        );

        if ($ownerId === $userId) {
            wp_delete_attachment($attachmentId, true);
        }
    }

    /**
     * @param mixed $idOrEmail
     */
    private function resolveAvatarUserId($idOrEmail): int
    {
        if (is_numeric($idOrEmail)) {
            return absint($idOrEmail);
        }

        if ($idOrEmail instanceof \WP_User) {
            return (int) $idOrEmail->ID;
        }

        if ($idOrEmail instanceof \WP_Comment) {
            if ((int) $idOrEmail->user_id > 0) {
                return (int) $idOrEmail->user_id;
            }

            $idOrEmail = $idOrEmail->comment_author_email;
        }

        if (is_string($idOrEmail) && is_email($idOrEmail)) {
            $user = get_user_by('email', $idOrEmail);
            return $user instanceof \WP_User ? (int) $user->ID : 0;
        }

        return 0;
    }
}
