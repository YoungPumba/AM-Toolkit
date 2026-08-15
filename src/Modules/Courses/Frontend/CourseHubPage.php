<?php

namespace AMToolkit\Modules\Courses\Frontend;

use AMToolkit\Modules\Courses\Services\CourseCatalogService;
use WP_User;

defined('ABSPATH') || exit;

final class CourseHubPage
{
    private const ENDPOINT = 'kursy';
    private const REWRITE_VERSION = '1';

    public function __construct(private CourseCatalogService $courses)
    {
    }

    public function boot(): void
    {
        if (!class_exists('WooCommerce')) {
            return;
        }

        add_action('init', [$this, 'registerEndpoint']);
        add_action('init', [$this, 'maybeFlushRewriteRules'], 99);
        add_filter('woocommerce_get_query_vars', [$this, 'addQueryVar']);
        add_filter('woocommerce_account_menu_items', [$this, 'addMenuItem']);
        add_action('woocommerce_account_' . self::ENDPOINT . '_endpoint', [$this, 'output']);
        add_filter('template_include', [$this, 'accountTemplate'], 99);
        add_filter('am_toolkit_account_navigation_items', [$this, 'addAccountNavigationItem'], 10, 2);
        add_filter('am_toolkit_account_shortcut_configuration', [$this, 'shortcutConfiguration'], 10, 4);
        add_shortcode('am_courses_hub', [$this, 'render']);
        add_action('wp_enqueue_scripts', [$this, 'enqueueAssets']);
    }

    public function registerEndpoint(): void
    {
        add_rewrite_endpoint(self::ENDPOINT, EP_ROOT | EP_PAGES);
    }

    /** @param array<string, string> $queryVars */
    public function addQueryVar(array $queryVars): array
    {
        $queryVars[self::ENDPOINT] = self::ENDPOINT;

        return $queryVars;
    }

    /** @param array<string, string> $items */
    public function addMenuItem(array $items): array
    {
        $result = [];

        foreach ($items as $key => $label) {
            $result[$key] = $label;

            if ($key === 'dashboard') {
                $result[self::ENDPOINT] = __('Kursy', 'am-toolkit');
            }
        }

        if (!isset($result[self::ENDPOINT])) {
            $result[self::ENDPOINT] = __('Kursy', 'am-toolkit');
        }

        return $result;
    }

    /**
     * @param list<array{label: string, url: string, icon: string, current: bool}> $items
     * @return list<array{label: string, url: string, icon: string, current: bool}>
     */
    public function addAccountNavigationItem(array $items, string $accountUrl): array
    {
        $courseItem = [
            'label' => __('Kursy', 'am-toolkit'),
            'url' => wc_get_endpoint_url(self::ENDPOINT, '', $accountUrl),
            'icon' => 'courses',
            'current' => function_exists('is_wc_endpoint_url') && is_wc_endpoint_url(self::ENDPOINT),
        ];
        $result = [];
        $inserted = false;

        foreach ($items as $item) {
            $result[] = $item;

            if ($item['icon'] === 'products') {
                $result[] = $courseItem;
                $inserted = true;
            }
        }

        if (!$inserted) {
            $result[] = $courseItem;
        }

        return $result;
    }

    /**
     * @param mixed $configuration
     * @return array{title: string, description: string, url: string}|null
     */
    public function shortcutConfiguration(
        mixed $configuration,
        string $type,
        WP_User $user,
        string $accountUrl
    ): ?array {
        if ($type !== 'courses') {
            return is_array($configuration) ? $configuration : null;
        }

        $courses = $this->courses->coursesForUser($user->ID);
        $count = is_wp_error($courses) ? 0 : count($courses);

        return [
            'title' => __('Kursy', 'am-toolkit'),
            'description' => sprintf(
                /* translators: %d: number of courses assigned to the customer. */
                _n('Przypisany kurs: %d', 'Przypisane kursy: %d', $count, 'am-toolkit'),
                $count
            ),
            'url' => wc_get_endpoint_url(self::ENDPOINT, '', $accountUrl),
        ];
    }

    public function accountTemplate(string $template): string
    {
        if (
            is_admin() ||
            !is_user_logged_in() ||
            !function_exists('is_wc_endpoint_url') ||
            !is_wc_endpoint_url(self::ENDPOINT)
        ) {
            return $template;
        }

        $pluginTemplate = AM_TOOLKIT_PATH . 'templates/account/courses.php';

        return file_exists($pluginTemplate) ? $pluginTemplate : $template;
    }

    public function output(): void
    {
        echo $this->render(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }

    /** @param array<string, mixed> $attributes */
    public function render(array $attributes = []): string
    {
        if (!is_user_logged_in()) {
            return '';
        }

        $this->enqueueAssets(true);
        $publicId = $this->endpointValue();

        return $publicId === ''
            ? $this->renderHub(get_current_user_id())
            : $this->renderCourse(get_current_user_id(), $publicId);
    }

    public function enqueueAssets(bool $force = false): void
    {
        $isCoursesEndpoint = function_exists('is_wc_endpoint_url')
            && is_wc_endpoint_url(self::ENDPOINT);
        $isAccountDashboard = function_exists('is_account_page')
            && is_account_page()
            && function_exists('is_wc_endpoint_url')
            && !is_wc_endpoint_url();

        if (
            !$force &&
            !$isCoursesEndpoint &&
            !$isAccountDashboard
        ) {
            return;
        }

        $relativePath = 'assets/css/courses.css';
        $absolutePath = AM_TOOLKIT_PATH . $relativePath;

        wp_enqueue_style(
            'am-toolkit-courses',
            AM_TOOLKIT_URL . $relativePath,
            ['am-toolkit-account'],
            file_exists($absolutePath) ? (string) filemtime($absolutePath) : AM_TOOLKIT_VERSION
        );
    }

    public function maybeFlushRewriteRules(): void
    {
        if (
            get_option('amt_courses_hub_rewrite_version') === self::REWRITE_VERSION &&
            $this->hasEndpointRewriteRule()
        ) {
            return;
        }

        flush_rewrite_rules(false);
        update_option('amt_courses_hub_rewrite_version', self::REWRITE_VERSION, false);
    }

    private function hasEndpointRewriteRule(): bool
    {
        $rules = get_option('rewrite_rules', []);

        if (!is_array($rules)) {
            return false;
        }

        foreach (array_keys($rules) as $rule) {
            if (is_string($rule) && str_contains($rule, self::ENDPOINT)) {
                return true;
            }
        }

        return false;
    }

    private function endpointValue(): string
    {
        global $wp;

        $value = isset($wp->query_vars[self::ENDPOINT])
            ? (string) $wp->query_vars[self::ENDPOINT]
            : '';

        return sanitize_text_field(rawurldecode($value));
    }

    private function renderHub(int $userId): string
    {
        $courses = $this->courses->coursesForUser($userId);

        if (is_wp_error($courses)) {
            return $this->renderError($courses->get_error_message());
        }

        $groups = [
            'active' => __('Aktywne kursy', 'am-toolkit'),
            'completed' => __('Ukończone kursy', 'am-toolkit'),
            'scheduled' => __('Dostęp rozpocznie się później', 'am-toolkit'),
            'expired' => __('Wygasłe kursy', 'am-toolkit'),
        ];
        $groupedCourses = [
            'active' => [],
            'completed' => [],
            'scheduled' => [],
            'expired' => [],
        ];

        foreach ($courses as $course) {
            $state = isset($course['access_state']) ? (string) $course['access_state'] : 'expired';

            if (!isset($groupedCourses[$state])) {
                $state = 'expired';
            }

            $groupedCourses[$state][] = $course;
        }

        ob_start();
        ?>
        <section class="am-courses" aria-labelledby="am-courses-title">
            <header class="am-courses__hero">
                <span class="am-courses__eyebrow"><?php echo esc_html__('Twoja przestrzeń do nauki', 'am-toolkit'); ?></span>
                <h1 id="am-courses-title"><?php echo esc_html__('Moje kursy', 'am-toolkit'); ?></h1>
                <p><?php echo esc_html__('Tutaj znajdziesz swoje kursy i ich aktualny program.', 'am-toolkit'); ?></p>
            </header>

            <?php if ($courses === []) : ?>
                <div class="am-courses__empty" role="status">
                    <span class="am-courses__empty-icon" aria-hidden="true">♡</span>
                    <h2><?php echo esc_html__('Nie masz jeszcze przypisanych kursów', 'am-toolkit'); ?></h2>
                    <p><?php echo esc_html__('Gdy otrzymasz dostęp do kursu, pojawi się właśnie tutaj.', 'am-toolkit'); ?></p>
                </div>
            <?php else : ?>
                <?php foreach ($groups as $state => $title) : ?>
                    <?php $stateCourses = $groupedCourses[$state]; ?>
                    <?php if ($stateCourses === []) : ?>
                        <?php continue; ?>
                    <?php endif; ?>
                    <section class="am-courses__group" aria-labelledby="am-courses-<?php echo esc_attr($state); ?>">
                        <h2 id="am-courses-<?php echo esc_attr($state); ?>"><?php echo esc_html($title); ?></h2>
                        <div class="am-courses__grid">
                            <?php foreach ($stateCourses as $course) : ?>
                                <?php echo $this->renderCourseCard($course); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                            <?php endforeach; ?>
                        </div>
                    </section>
                <?php endforeach; ?>
            <?php endif; ?>
        </section>
        <?php

        return (string) ob_get_clean();
    }

    /** @param array<string, mixed> $course */
    private function renderCourseCard(array $course): string
    {
        $state = isset($course['access_state']) ? (string) $course['access_state'] : 'expired';
        $canOpen = !empty($course['can_open']);
        $url = $canOpen ? $this->courseUrl((string) ($course['public_id'] ?? '')) : '';
        $labels = [
            'active' => __('Aktywny', 'am-toolkit'),
            'completed' => __('Ukończony', 'am-toolkit'),
            'scheduled' => __('Zaplanowany', 'am-toolkit'),
            'expired' => __('Dostęp wygasł', 'am-toolkit'),
        ];
        $image = $this->courseImage((int) ($course['image_attachment_id'] ?? 0), (string) ($course['title'] ?? ''));

        ob_start();
        ?>
        <article class="am-course-card am-course-card--<?php echo esc_attr($state); ?>">
            <div class="am-course-card__media">
                <?php echo $image; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            </div>
            <div class="am-course-card__body">
                <span class="am-course-card__status"><?php echo esc_html($labels[$state] ?? $labels['expired']); ?></span>
                <h3><?php echo esc_html((string) ($course['title'] ?? '')); ?></h3>
                <?php if ($url !== '') : ?>
                    <a class="am-course-card__action" href="<?php echo esc_url($url); ?>">
                        <?php echo esc_html__('Zobacz program', 'am-toolkit'); ?>
                        <span aria-hidden="true">→</span>
                    </a>
                <?php else : ?>
                    <p class="am-course-card__notice">
                        <?php echo esc_html($state === 'scheduled'
                            ? __('Program będzie dostępny od daty rozpoczęcia dostępu.', 'am-toolkit')
                            : __('Program jest ukryty, ponieważ nie masz aktywnego dostępu.', 'am-toolkit')); ?>
                    </p>
                <?php endif; ?>
            </div>
        </article>
        <?php

        return (string) ob_get_clean();
    }

    private function renderCourse(int $userId, string $publicId): string
    {
        $course = $this->courses->courseForUser($userId, $publicId);

        if (is_wp_error($course)) {
            status_header($course->get_error_code() === 'am_toolkit_course_not_available' ? 404 : 503);

            return $this->renderError($course->get_error_message());
        }

        $program = isset($course['program']) && is_array($course['program'])
            ? $course['program']
            : ['sections' => [], 'lessons' => []];
        $sections = isset($program['sections']) && is_array($program['sections']) ? $program['sections'] : [];
        $lessons = isset($program['lessons']) && is_array($program['lessons']) ? $program['lessons'] : [];

        ob_start();
        ?>
        <article class="am-course" aria-labelledby="am-course-title">
            <a class="am-course__back" href="<?php echo esc_url($this->hubUrl()); ?>">← <?php echo esc_html__('Wróć do kursów', 'am-toolkit'); ?></a>
            <header class="am-course__header">
                <div class="am-course__header-copy">
                    <span class="am-courses__eyebrow"><?php echo esc_html__('Twój kurs', 'am-toolkit'); ?></span>
                    <h1 id="am-course-title"><?php echo esc_html((string) ($course['title'] ?? '')); ?></h1>
                    <?php if (!empty($course['description'])) : ?>
                        <p><?php echo nl2br(esc_html((string) $course['description'])); ?></p>
                    <?php endif; ?>
                </div>
                <div class="am-course__cover">
                    <?php echo $this->courseImage((int) ($course['image_attachment_id'] ?? 0), (string) ($course['title'] ?? '')); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                </div>
            </header>

            <section class="am-course__program" aria-labelledby="am-course-program-title">
                <header class="am-course__program-header">
                    <span class="am-courses__eyebrow"><?php echo esc_html__('Plan nauki', 'am-toolkit'); ?></span>
                    <h2 id="am-course-program-title"><?php echo esc_html__('Program kursu', 'am-toolkit'); ?></h2>
                </header>

                <?php if ($sections === [] && $lessons === []) : ?>
                    <div class="am-courses__empty" role="status">
                        <h3><?php echo esc_html__('Program jest jeszcze pusty', 'am-toolkit'); ?></h3>
                        <p><?php echo esc_html__('Opublikowane lekcje pojawią się tutaj, gdy będą gotowe.', 'am-toolkit'); ?></p>
                    </div>
                <?php else : ?>
                    <?php foreach ($sections as $section) : ?>
                        <?php echo $this->renderSection($section); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    <?php endforeach; ?>
                    <?php if ($lessons !== []) : ?>
                        <?php echo $this->renderLessonList($lessons, __('Lekcje', 'am-toolkit')); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    <?php endif; ?>
                <?php endif; ?>
            </section>
        </article>
        <?php

        return (string) ob_get_clean();
    }

    /** @param array<string, mixed> $section */
    private function renderSection(array $section): string
    {
        $lessons = isset($section['lessons']) && is_array($section['lessons'])
            ? $section['lessons']
            : [];

        ob_start();
        ?>
        <section class="am-course-section">
            <header class="am-course-section__header">
                <h3><?php echo esc_html((string) ($section['title'] ?? '')); ?></h3>
                <?php if (!empty($section['description'])) : ?>
                    <p><?php echo nl2br(esc_html((string) $section['description'])); ?></p>
                <?php endif; ?>
            </header>
            <?php echo $this->renderLessonList($lessons); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        </section>
        <?php

        return (string) ob_get_clean();
    }

    /** @param list<array<string, mixed>> $lessons */
    private function renderLessonList(array $lessons, string $title = ''): string
    {
        ob_start();
        ?>
        <?php if ($title !== '') : ?>
            <h3 class="am-course-section__standalone-title"><?php echo esc_html($title); ?></h3>
        <?php endif; ?>
        <?php if ($lessons === []) : ?>
            <p class="am-course-section__empty"><?php echo esc_html__('W tej części nie ma jeszcze opublikowanych lekcji.', 'am-toolkit'); ?></p>
        <?php else : ?>
            <ol class="am-course-lessons">
                <?php foreach ($lessons as $index => $lesson) : ?>
                    <li class="am-course-lesson">
                        <span class="am-course-lesson__number" aria-hidden="true"><?php echo esc_html((string) ($index + 1)); ?></span>
                        <span class="am-course-lesson__copy">
                            <strong><?php echo esc_html((string) ($lesson['title'] ?? '')); ?></strong>
                            <span>
                                <?php echo esc_html($this->lessonMeta($lesson)); ?>
                            </span>
                        </span>
                    </li>
                <?php endforeach; ?>
            </ol>
        <?php endif; ?>
        <?php

        return (string) ob_get_clean();
    }

    /** @param array<string, mixed> $lesson */
    private function lessonMeta(array $lesson): string
    {
        $parts = [];
        $duration = isset($lesson['duration_seconds']) ? (int) $lesson['duration_seconds'] : 0;

        if ($duration > 0) {
            $minutes = max(1, (int) ceil($duration / 60));
            $parts[] = sprintf(
                /* translators: %d: lesson duration in minutes. */
                _n('%d minuta', '%d minut', $minutes, 'am-toolkit'),
                $minutes
            );
        }

        if (!empty($lesson['is_required'])) {
            $parts[] = __('lekcja wymagana', 'am-toolkit');
        }

        return $parts === [] ? __('Lekcja kursu', 'am-toolkit') : implode(' · ', $parts);
    }

    private function courseImage(int $attachmentId, string $title): string
    {
        if ($attachmentId > 0) {
            $image = wp_get_attachment_image(
                $attachmentId,
                'large',
                false,
                ['class' => 'am-course-image', 'alt' => $title]
            );

            if ($image !== '') {
                return $image;
            }
        }

        return '<span class="am-course-image am-course-image--placeholder" aria-hidden="true">AM</span>';
    }

    private function courseUrl(string $publicId): string
    {
        return wc_get_endpoint_url(self::ENDPOINT, rawurlencode($publicId), wc_get_page_permalink('myaccount'));
    }

    private function hubUrl(): string
    {
        return wc_get_endpoint_url(self::ENDPOINT, '', wc_get_page_permalink('myaccount'));
    }

    private function renderError(string $message): string
    {
        return sprintf(
            '<div class="am-courses am-courses__error" role="alert"><h1>%1$s</h1><p>%2$s</p><a href="%3$s">%4$s</a></div>',
            esc_html__('Nie udało się otworzyć kursu', 'am-toolkit'),
            esc_html($message),
            esc_url($this->hubUrl()),
            esc_html__('Wróć do listy kursów', 'am-toolkit')
        );
    }
}
