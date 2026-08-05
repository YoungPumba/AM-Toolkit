<?php

defined('ABSPATH') || exit;

get_header();
?>
<main id="primary" class="am-account-details-page">
    <div class="am-account-details-page__inner">
        <?php echo do_shortcode('[am_account_details]'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
    </div>
</main>
<?php
get_footer();
