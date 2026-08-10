<?php

namespace AMToolkit\Modules\Account;

use AMToolkit\Core\Authorization;
use WP_User;

defined('ABSPATH') || exit;

final class ManualProductAssignments
{
    private const META_KEY = '_amt_assigned_products';
    private const NONCE_ACTION = 'amt_save_assigned_products';
    private const NONCE_NAME = 'amt_assigned_products_nonce';

    public function boot(): void
    {
        add_action('show_user_profile', [$this, 'renderProfileFields']);
        add_action('edit_user_profile', [$this, 'renderProfileFields']);
        add_action('personal_options_update', [$this, 'saveProfileFields']);
        add_action('edit_user_profile_update', [$this, 'saveProfileFields']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAdminAssets']);
    }

    public function enqueueAdminAssets(string $hook): void
    {
        if (!in_array($hook, ['profile.php', 'user-edit.php'], true) || !$this->canManageAssignments()) {
            return;
        }

        wp_enqueue_script('wc-enhanced-select');
        wp_enqueue_style('woocommerce_admin_styles');
    }

    public function renderProfileFields(WP_User $user): void
    {
        if (!$this->canManageAssignments() || !current_user_can('edit_user', $user->ID)) {
            return;
        }

        $assignments = self::assignments($user->ID);
        wp_nonce_field(self::NONCE_ACTION, self::NONCE_NAME);
        ?>
        <h2><?php echo esc_html__('AM Toolkit — produkty użytkownika', 'am-toolkit'); ?></h2>
        <table class="form-table" role="presentation">
            <tr>
                <th>
                    <label for="amt-assigned-products">
                        <?php echo esc_html__('Ręcznie przypisane produkty', 'am-toolkit'); ?>
                    </label>
                </th>
                <td>
                    <select
                        id="amt-assigned-products"
                        class="wc-product-search"
                        name="amt_assigned_products[]"
                        multiple="multiple"
                        style="width: min(700px, 100%);"
                        data-placeholder="<?php echo esc_attr__('Wyszukaj produkt…', 'am-toolkit'); ?>"
                        data-action="woocommerce_json_search_products_and_variations"
                    >
                        <?php foreach ($assignments as $productId => $assignedAt) : ?>
                            <?php $product = wc_get_product($productId); ?>
                            <?php if ($product) : ?>
                                <option value="<?php echo esc_attr((string) $productId); ?>" selected="selected">
                                    <?php echo esc_html(wp_strip_all_tags($product->get_formatted_name())); ?>
                                </option>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                    <p class="description">
                        <?php echo esc_html__('Produkty pojawią się w sekcji „Moje produkty” bez tworzenia zamówienia. Usunięcie pozycji z pola odbiera ręczne przypisanie.', 'am-toolkit'); ?>
                    </p>
                </td>
            </tr>
        </table>
        <?php
    }

    public function saveProfileFields(int $userId): void
    {
        if (!$this->canManageAssignments() || !current_user_can('edit_user', $userId)) {
            return;
        }

        $nonce = isset($_POST[self::NONCE_NAME])
            ? sanitize_text_field(wp_unslash($_POST[self::NONCE_NAME]))
            : '';

        if ($nonce === '' || !wp_verify_nonce($nonce, self::NONCE_ACTION)) {
            return;
        }

        $submitted = isset($_POST['amt_assigned_products'])
            ? (array) wp_unslash($_POST['amt_assigned_products'])
            : [];
        $productIds = array_values(array_unique(array_filter(array_map('absint', $submitted))));
        $existing = self::assignments($userId);
        $updated = [];

        foreach ($productIds as $productId) {
            $product = wc_get_product($productId);

            if (!$product) {
                continue;
            }

            $normalizedId = $product->is_type('variation')
                ? (int) $product->get_parent_id()
                : $productId;

            if (!$normalizedId) {
                continue;
            }

            $updated[$normalizedId] = $existing[$normalizedId] ?? time();
        }

        if ($updated === []) {
            delete_user_meta($userId, self::META_KEY);
            return;
        }

        update_user_meta($userId, self::META_KEY, $updated);
    }

    /** @return array<int, int> Product ID => assignment timestamp. */
    public static function assignments(int $userId): array
    {
        $stored = get_user_meta($userId, self::META_KEY, true);

        if (!is_array($stored)) {
            return [];
        }

        $assignments = [];

        foreach ($stored as $productId => $assignedAt) {
            $productId = absint($productId);

            if ($productId) {
                $assignments[$productId] = max(1, absint($assignedAt));
            }
        }

        arsort($assignments, SORT_NUMERIC);

        return $assignments;
    }

    private function canManageAssignments(): bool
    {
        return Authorization::canManageAccess();
    }
}
