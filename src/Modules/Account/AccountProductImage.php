<?php

namespace AMToolkit\Modules\Account;

use WC_Product;

defined('ABSPATH') || exit;

final class AccountProductImage
{
    private const META_KEY = '_amt_account_product_image_id';
    private const NONCE_ACTION = 'amt_save_account_product_image';
    private const NONCE_NAME = 'amt_account_product_image_nonce';

    public function boot(): void
    {
        add_action('woocommerce_product_options_general_product_data', [$this, 'renderField']);
        add_action('woocommerce_admin_process_product_object', [$this, 'saveField']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAdminAssets']);
    }

    public function enqueueAdminAssets(string $hook): void
    {
        if (!in_array($hook, ['post.php', 'post-new.php'], true) || !$this->isProductScreen()) {
            return;
        }

        wp_enqueue_media();

        wp_enqueue_style(
            'am-toolkit-admin-account-product-image',
            AM_TOOLKIT_URL . 'assets/css/admin-account-product-image.css',
            [],
            $this->assetVersion('assets/css/admin-account-product-image.css')
        );

        wp_enqueue_script(
            'am-toolkit-admin-account-product-image',
            AM_TOOLKIT_URL . 'assets/js/admin-account-product-image.js',
            ['jquery'],
            $this->assetVersion('assets/js/admin-account-product-image.js'),
            true
        );
    }

    public function renderField(): void
    {
        global $post;

        if (!$post || $post->post_type !== 'product') {
            return;
        }

        $attachmentId = absint(get_post_meta($post->ID, self::META_KEY, true));
        $preview = $attachmentId && wp_attachment_is_image($attachmentId)
            ? wp_get_attachment_image($attachmentId, 'medium', false, [
                'class' => 'amt-account-product-image__preview-image',
            ])
            : '';

        wp_nonce_field(self::NONCE_ACTION, self::NONCE_NAME);
        ?>
        <div
            class="options_group amt-account-product-image"
            data-placeholder="<?php echo esc_attr__('Brak dodatkowego obrazu', 'am-toolkit'); ?>"
        >
            <p class="form-field">
                <label for="amt-account-product-image-id">
                    <?php echo esc_html__('Obraz w panelu „Moje produkty”', 'am-toolkit'); ?>
                </label>

                <span class="amt-account-product-image__field">
                    <span class="amt-account-product-image__preview">
                        <?php if ($preview !== '') : ?>
                            <?php echo $preview; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                        <?php else : ?>
                            <span class="amt-account-product-image__placeholder">
                                <?php echo esc_html__('Brak dodatkowego obrazu', 'am-toolkit'); ?>
                            </span>
                        <?php endif; ?>
                    </span>

                    <input
                        id="amt-account-product-image-id"
                        type="hidden"
                        name="amt_account_product_image_id"
                        value="<?php echo esc_attr((string) $attachmentId); ?>"
                    >

                    <span class="amt-account-product-image__actions">
                        <button
                            type="button"
                            class="button amt-account-product-image__select"
                            data-frame-title="<?php echo esc_attr__('Wybierz obraz panelu konta', 'am-toolkit'); ?>"
                            data-frame-button="<?php echo esc_attr__('Użyj tego obrazu', 'am-toolkit'); ?>"
                        >
                            <?php echo esc_html__('Wybierz obraz', 'am-toolkit'); ?>
                        </button>

                        <button
                            type="button"
                            class="button-link-delete amt-account-product-image__remove"
                            <?php echo $attachmentId ? '' : 'hidden'; ?>
                        >
                            <?php echo esc_html__('Usuń obraz', 'am-toolkit'); ?>
                        </button>
                    </span>

                    <span class="description">
                        <?php
                        echo esc_html__(
                            'Najlepiej użyć poziomej grafiki w proporcji około 1.9:1, np. 1200 × 630 px lub 1600 × 840 px, zapisanej jako WebP. Ważne elementy i napisy pozostaw z dala od krawędzi. Obraz będzie używany wyłącznie w panelu „Moje produkty”. Gdy pole pozostanie puste, AM Toolkit użyje głównej miniatury produktu.',
                            'am-toolkit'
                        );
                        ?>
                    </span>
                </span>
            </p>
        </div>
        <?php
    }

    public function saveField(WC_Product $product): void
    {
        $nonce = isset($_POST[self::NONCE_NAME])
            ? sanitize_text_field(wp_unslash($_POST[self::NONCE_NAME]))
            : '';

        if (
            $nonce === '' ||
            !wp_verify_nonce($nonce, self::NONCE_ACTION) ||
            !current_user_can('edit_post', $product->get_id())
        ) {
            return;
        }

        $attachmentId = isset($_POST['amt_account_product_image_id'])
            ? absint(wp_unslash($_POST['amt_account_product_image_id']))
            : 0;

        if (!$attachmentId || !wp_attachment_is_image($attachmentId)) {
            $product->delete_meta_data(self::META_KEY);
            return;
        }

        $product->update_meta_data(self::META_KEY, $attachmentId);
    }

    public static function imageHtml(int $productId, string $size = 'large'): string
    {
        $attachmentId = absint(get_post_meta($productId, self::META_KEY, true));

        if (!$attachmentId || !wp_attachment_is_image($attachmentId)) {
            return '';
        }

        return (string) wp_get_attachment_image($attachmentId, $size, false, [
            'class'   => 'am-purchased-product__source-image',
            'loading' => 'lazy',
        ]);
    }

    private function isProductScreen(): bool
    {
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;

        return $screen && $screen->post_type === 'product';
    }

    private function assetVersion(string $relativePath): string
    {
        $absolutePath = AM_TOOLKIT_PATH . $relativePath;

        return file_exists($absolutePath)
            ? (string) filemtime($absolutePath)
            : AM_TOOLKIT_VERSION;
    }
}
