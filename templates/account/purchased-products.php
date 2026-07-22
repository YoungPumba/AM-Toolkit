<?php

defined('ABSPATH') || exit;

get_header();
?>
<main id="primary" class="am-products-page">
    <div class="am-products-page__inner">
        <?php echo do_shortcode('[am_account_purchased_products]'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
    </div>
</main>
<?php
get_footer();
