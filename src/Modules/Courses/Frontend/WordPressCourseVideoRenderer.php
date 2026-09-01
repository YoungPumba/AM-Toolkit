<?php

namespace AMToolkit\Modules\Courses\Frontend;

use AMToolkit\Modules\Courses\Contracts\CourseVideoRenderer;

defined('ABSPATH') || exit;

final class WordPressCourseVideoRenderer implements CourseVideoRenderer
{
    public function render(string $sourceUrl, array $context = []): string|\WP_Error
    {
        if ($sourceUrl === '') {
            return new \WP_Error(
                'am_toolkit_course_video_unavailable',
                __('Odtwarzacz nagrania jest teraz niedostępny.', 'am-toolkit')
            );
        }

        wp_enqueue_style('wp-mediaelement');
        wp_enqueue_script('wp-mediaelement');
        $poster = isset($context['poster']) && is_string($context['poster'])
            ? $context['poster']
            : '';
        $posterAttribute = $poster !== ''
            ? sprintf(' poster="%s"', esc_url($poster))
            : '';

        /*
         * wp_video_shortcode() rejects protected query-string URLs because
         * their path does not end in a media extension. The markup below uses
         * WordPress' standard class, so wp-mediaelement initializes the same
         * accessible player without guessing the file type from the URL.
         */
        return sprintf(
            '<div class="wp-video"><video class="wp-video-shortcode" width="1280" height="720" controls="controls" preload="metadata" playsinline="playsinline"%s><source type="video/mp4" src="%s" /></video></div>',
            $posterAttribute,
            esc_url($sourceUrl)
        );
    }
}
