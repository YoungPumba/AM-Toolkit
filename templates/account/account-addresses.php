<?php

defined('ABSPATH') || exit;

get_header();
?>
<main id="primary" class="am-account-addresses-page">
    <div class="am-account-addresses-page__inner">
        <?php echo do_shortcode('[am_account_addresses]'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
    </div>
</main>
<?php
get_footer();
