<?php

defined('ABSPATH') || exit;

get_header();
?>
<main id="primary" class="am-courses-page">
    <div class="am-courses-page__inner">
        <?php echo do_shortcode('[am_courses_hub]'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
    </div>
</main>
<?php
get_footer();
