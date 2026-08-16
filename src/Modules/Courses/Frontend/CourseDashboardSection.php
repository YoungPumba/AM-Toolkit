<?php

namespace AMToolkit\Modules\Courses\Frontend;

use AMToolkit\Modules\Courses\Services\CourseCatalogService;

defined('ABSPATH') || exit;

final class CourseDashboardSection
{
    private bool $rendered = false;

    public function __construct(private CourseCatalogService $courses)
    {
    }

    public function boot(): void
    {
        if (!class_exists('WooCommerce')) {
            return;
        }

        add_shortcode('am_courses_dashboard', [$this, 'render']);
        add_action('woocommerce_account_dashboard', [$this, 'output'], 5);
    }

    public function output(): void
    {
        echo $this->render(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }

    /** @param array<string, mixed> $attributes */
    public function render(array $attributes = []): string
    {
        if ($this->rendered || !is_user_logged_in()) {
            return '';
        }

        $this->rendered = true;
        $attributes = shortcode_atts(
            ['limit' => 3],
            $attributes,
            'am_courses_dashboard'
        );
        $limit = max(1, min(6, absint($attributes['limit'])));
        $courses = $this->courses->coursesForUser(get_current_user_id());
        $titleId = wp_unique_id('am-courses-dashboard-title-');

        if (!is_wp_error($courses)) {
            $courses = $this->dashboardCourses($courses, $limit);
        }

        ob_start();
        ?>
        <section class="am-courses-dashboard" aria-labelledby="<?php echo esc_attr($titleId); ?>">
            <header class="am-courses-dashboard__header">
                <div class="am-courses-dashboard__heading">
                    <span class="am-courses-dashboard__eyebrow"><?php echo esc_html__('Twoja nauka', 'am-toolkit'); ?></span>
                    <h2 id="<?php echo esc_attr($titleId); ?>"><?php echo esc_html__('Twoje kursy', 'am-toolkit'); ?></h2>
                    <p><?php echo esc_html__('Wróć do aktywnego programu albo sprawdź, co czeka na Ciebie dalej.', 'am-toolkit'); ?></p>
                </div>
                <a class="am-courses-dashboard__all" href="<?php echo esc_url($this->hubUrl()); ?>">
                    <?php echo esc_html__('Wszystkie kursy', 'am-toolkit'); ?>
                    <?php echo CourseIcon::render(CourseIcon::ARROW_RIGHT); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                </a>
            </header>

            <?php if (is_wp_error($courses)) : ?>
                <p class="am-courses-dashboard__notice" role="status">
                    <?php echo esc_html__('Nie udało się teraz wczytać podglądu kursów. Pełna lista nadal jest dostępna w sekcji Kursy.', 'am-toolkit'); ?>
                </p>
            <?php elseif ($courses === []) : ?>
                <div class="am-courses-dashboard__empty" role="status">
                    <span aria-hidden="true">♡</span>
                    <div>
                        <strong><?php echo esc_html__('Nie masz teraz aktywnego kursu', 'am-toolkit'); ?></strong>
                        <p><?php echo esc_html__('Gdy otrzymasz dostęp, najważniejszy kurs pojawi się tutaj.', 'am-toolkit'); ?></p>
                    </div>
                </div>
            <?php else : ?>
                <div class="am-courses-dashboard__grid">
                    <?php foreach ($courses as $course) : ?>
                        <?php echo $this->courseCard($course); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
        <?php

        return (string) ob_get_clean();
    }

    /**
     * @param list<array<string, mixed>> $courses
     * @return list<array<string, mixed>>
     */
    private function dashboardCourses(array $courses, int $limit): array
    {
        $courses = array_values(array_filter(
            $courses,
            static fn(array $course): bool => ($course['access_state'] ?? 'expired') !== 'expired'
        ));
        $priority = [
            'active' => 0,
            'scheduled' => 1,
            'completed' => 2,
        ];

        usort(
            $courses,
            static function (array $left, array $right) use ($priority): int {
                $leftState = isset($left['access_state']) ? (string) $left['access_state'] : 'expired';
                $rightState = isset($right['access_state']) ? (string) $right['access_state'] : 'expired';

                return ($priority[$leftState] ?? 9) <=> ($priority[$rightState] ?? 9);
            }
        );

        return array_slice($courses, 0, $limit);
    }

    /** @param array<string, mixed> $course */
    private function courseCard(array $course): string
    {
        $state = isset($course['access_state']) ? (string) $course['access_state'] : 'expired';
        $canOpen = !empty($course['can_open']);
        $tag = $canOpen ? 'a' : 'article';
        $labels = [
            'active' => __('Aktywny', 'am-toolkit'),
            'completed' => __('Ukończony', 'am-toolkit'),
            'scheduled' => __('Zaplanowany', 'am-toolkit'),
        ];

        ob_start();
        ?>
        <<?php echo esc_attr($tag); ?>
            class="am-courses-dashboard-card am-courses-dashboard-card--<?php echo esc_attr($state); ?>"
            <?php if ($canOpen) : ?>
                href="<?php echo esc_url($this->courseUrl((string) ($course['public_id'] ?? ''))); ?>"
            <?php endif; ?>
        >
            <span class="am-courses-dashboard-card__media">
                <?php echo $this->courseImage((int) ($course['image_attachment_id'] ?? 0), (string) ($course['title'] ?? '')); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            </span>
            <span class="am-courses-dashboard-card__copy">
                <span class="am-courses-dashboard-card__status"><?php echo esc_html($labels[$state] ?? $labels['active']); ?></span>
                <strong><?php echo esc_html((string) ($course['title'] ?? '')); ?></strong>
                <?php if (isset($course['nearest_meeting']) && is_array($course['nearest_meeting'])) : ?>
                    <span class="am-courses-dashboard-card__meeting">
                        <?php echo CourseIcon::render(CourseIcon::CALENDAR); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                        <?php echo esc_html($this->meetingDate($course['nearest_meeting'])); ?>
                    </span>
                <?php endif; ?>
                <small>
                    <?php echo esc_html($canOpen
                        ? __('Otwórz program kursu', 'am-toolkit')
                        : __('Program będzie dostępny od daty rozpoczęcia', 'am-toolkit')); ?>
                </small>
            </span>
            <?php if ($canOpen) : ?>
                <span class="am-courses-dashboard-card__arrow" aria-hidden="true">
                    <?php echo CourseIcon::render(CourseIcon::ARROW_RIGHT); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                </span>
            <?php endif; ?>
        </<?php echo esc_attr($tag); ?>>
        <?php

        return (string) ob_get_clean();
    }

    private function courseImage(int $attachmentId, string $title): string
    {
        if ($attachmentId > 0) {
            $image = wp_get_attachment_image(
                $attachmentId,
                'medium',
                false,
                ['class' => 'am-course-image', 'alt' => $title]
            );

            if ($image !== '') {
                return $image;
            }
        }

        return '<span class="am-course-image am-course-image--placeholder" aria-hidden="true">AM</span>';
    }

    /** @param array<string, mixed> $meeting */
    private function meetingDate(array $meeting): string
    {
        try {
            $timezone = new \DateTimeZone((string) ($meeting['display_timezone'] ?? 'Europe/Warsaw'));
            $date = new \DateTimeImmutable((string) ($meeting['starts_at_utc'] ?? ''), new \DateTimeZone('UTC'));
            return wp_date('j.m.Y · H:i', $date->getTimestamp(), $timezone);
        } catch (\Throwable) {
            return '';
        }
    }

    private function courseUrl(string $publicId): string
    {
        return wc_get_endpoint_url('kursy', rawurlencode($publicId), wc_get_page_permalink('myaccount'));
    }

    private function hubUrl(): string
    {
        return wc_get_endpoint_url('kursy', '', wc_get_page_permalink('myaccount'));
    }
}
