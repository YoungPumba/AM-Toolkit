<?php

defined('ABSPATH') || exit;

get_header();
?>
<main id="primary" class="am-orders-page">
    <div class="am-orders-page__inner">
        <?php echo do_shortcode('[am_account_orders]'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
    </div>
</main>
<?php
get_footer();
