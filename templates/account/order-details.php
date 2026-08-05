<?php

defined('ABSPATH') || exit;

get_header();
?>
<main id="primary" class="am-order-details-page">
    <div class="am-order-details-page__inner">
        <?php echo do_shortcode('[am_account_order_details]'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
    </div>
</main>
<?php
get_footer();
