<?php

namespace AMToolkit\Modules\Courses\Frontend;

use AMToolkit\Modules\Courses\Contracts\CourseVideoRenderer;
use AMToolkit\Modules\Courses\Services\CourseCatalogService;
use AMToolkit\Modules\Courses\Services\CourseProgressService;
use WP_User;

defined('ABSPATH') || exit;

final class CourseHubPage
{
    private const ENDPOINT = 'kursy';
    private const REWRITE_VERSION = '1';

    public function __construct(
        private CourseCatalogService $courses,
        private CourseAssetController $assets,
        private CourseVideoRenderer $videoRenderer,
        private ?CourseProgressService $progress = null,
        private ?CourseProgressController $progressController = null
    ) {
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
        $segments = $this->endpointSegments();
        $userId = get_current_user_id();

        if ($segments === []) {
            return $this->renderHub($userId);
        }

        if (count($segments) === 1) {
            return $this->renderCourse($userId, $segments[0]);
        }

        if (count($segments) === 3 && $segments[1] === 'lekcja') {
            return $this->renderLesson($userId, $segments[0], $segments[2]);
        }

        status_header(404);

        return $this->renderError(__('Ten adres kursu jest nieprawidłowy.', 'am-toolkit'));
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

        $scriptPath = 'assets/js/course-player.js';
        $absoluteScriptPath = AM_TOOLKIT_PATH . $scriptPath;
        wp_enqueue_script(
            'am-toolkit-course-player',
            AM_TOOLKIT_URL . $scriptPath,
            [],
            file_exists($absoluteScriptPath) ? (string) filemtime($absoluteScriptPath) : AM_TOOLKIT_VERSION,
            true
        );

        if ($this->progressController !== null) {
            wp_localize_script('am-toolkit-course-player', 'amToolkitCourseProgress', [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'action' => CourseProgressController::ACTION,
                'nonce' => $this->progressController->nonce(),
                'checkpointSeconds' => 15,
                'messages' => [
                    'saving' => __('Zapisywanie postępu…', 'am-toolkit'),
                    'saved' => __('Postęp zapisany.', 'am-toolkit'),
                    'error' => __('Nie udało się zapisać postępu. Spróbujemy ponownie.', 'am-toolkit'),
                    'completed' => __('Lekcja ukończona!', 'am-toolkit'),
                ],
            ]);
        }
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

    /** @return list<string> */
    private function endpointSegments(): array
    {
        global $wp;

        $value = isset($wp->query_vars[self::ENDPOINT])
            ? (string) $wp->query_vars[self::ENDPOINT]
            : '';

        $segments = array_map(
            static fn (string $segment): string => sanitize_text_field(rawurldecode($segment)),
            explode('/', trim($value, '/'))
        );

        return array_values(array_filter(
            $segments,
            static fn (string $segment): bool => $segment !== ''
        ));
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
        $coursePublicId = (string) ($course['public_id'] ?? '');
        $progress = isset($course['progress']) && is_array($course['progress']) ? $course['progress'] : [];
        $nextLesson = (string) ($progress['next_lesson_public_id'] ?? '');
        $url = $canOpen
            ? ($nextLesson !== ''
                ? $this->lessonUrl($coursePublicId, $nextLesson)
                : $this->courseUrl($coursePublicId))
            : '';
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
                <?php if ($canOpen && isset($progress['progress_percent'])) : ?>
                    <div class="am-course-card__progress" aria-label="<?php echo esc_attr__('Postęp kursu', 'am-toolkit'); ?>">
                        <span style="width: <?php echo esc_attr((string) (int) $progress['progress_percent']); ?>%"></span>
                    </div>
                    <small><?php echo esc_html(sprintf(__('Ukończono: %d%%', 'am-toolkit'), (int) $progress['progress_percent'])); ?></small>
                <?php endif; ?>
                <?php if ($url !== '') : ?>
                    <a class="am-course-card__action" href="<?php echo esc_url($url); ?>">
                        <?php echo esc_html(($progress['next_action'] ?? '') === 'continue'
                            ? __('Kontynuuj', 'am-toolkit')
                            : (($progress['next_action'] ?? '') === 'start'
                                ? __('Rozpocznij kurs', 'am-toolkit')
                                : __('Zobacz program', 'am-toolkit'))); ?>
                        <?php echo CourseIcon::render(CourseIcon::ARROW_RIGHT); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
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
        $progress = isset($course['progress']) && is_array($course['progress']) ? $course['progress'] : [];
        $nextLesson = (string) ($progress['next_lesson_public_id'] ?? '');

        ob_start();
        ?>
        <article class="am-course" aria-labelledby="am-course-title">
            <a class="am-course__back" href="<?php echo esc_url($this->hubUrl()); ?>">
                <?php echo CourseIcon::render(CourseIcon::ARROW_LEFT); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                <?php echo esc_html__('Wróć do kursów', 'am-toolkit'); ?>
            </a>
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

            <?php if ($progress !== []) : ?>
                <section class="am-course__progress" aria-label="<?php echo esc_attr__('Postęp kursu', 'am-toolkit'); ?>">
                    <div>
                        <strong><?php echo esc_html(!empty($progress['course_completed']) ? __('Kurs ukończony', 'am-toolkit') : __('Twój postęp', 'am-toolkit')); ?></strong>
                        <span><?php echo esc_html(sprintf(
                            __('%1$d z %2$d wymaganych lekcji', 'am-toolkit'),
                            (int) ($progress['required_completed'] ?? 0),
                            (int) ($progress['required_total'] ?? 0)
                        )); ?></span>
                    </div>
                    <div class="am-course__progress-bar"><span style="width: <?php echo esc_attr((string) (int) ($progress['progress_percent'] ?? 0)); ?>%"></span></div>
                    <?php if ($nextLesson !== '') : ?>
                        <a href="<?php echo esc_url($this->lessonUrl($publicId, $nextLesson)); ?>">
                            <?php echo esc_html(($progress['next_action'] ?? '') === 'continue' ? __('Kontynuuj naukę', 'am-toolkit') : __('Rozpocznij kurs', 'am-toolkit')); ?>
                            <?php echo CourseIcon::render(CourseIcon::ARROW_RIGHT); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                        </a>
                    <?php endif; ?>
                </section>
            <?php endif; ?>

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
                        <?php echo $this->renderSection($section, $publicId); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    <?php endforeach; ?>
                    <?php if ($lessons !== []) : ?>
                        <?php echo $this->renderLessonList($lessons, $publicId, __('Lekcje', 'am-toolkit')); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    <?php endif; ?>
                <?php endif; ?>
            </section>
        </article>
        <?php

        return (string) ob_get_clean();
    }

    /** @param array<string, mixed> $section */
    private function renderSection(array $section, string $coursePublicId): string
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
            <?php echo $this->renderLessonList($lessons, $coursePublicId); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        </section>
        <?php

        return (string) ob_get_clean();
    }

    /** @param list<array<string, mixed>> $lessons */
    private function renderLessonList(array $lessons, string $coursePublicId, string $title = ''): string
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
                    <?php $lessonStatus = (string) ($lesson['progress_status'] ?? 'no_record'); ?>
                    <li>
                        <a class="am-course-lesson am-course-lesson--<?php echo esc_attr($lessonStatus); ?>" href="<?php echo esc_url($this->lessonUrl($coursePublicId, (string) ($lesson['public_id'] ?? ''))); ?>">
                            <span class="am-course-lesson__number" aria-hidden="true"><?php echo esc_html($lessonStatus === 'completed' ? '✓' : (string) ($index + 1)); ?></span>
                            <span class="am-course-lesson__copy">
                                <strong><?php echo esc_html((string) ($lesson['title'] ?? '')); ?></strong>
                                <span><?php echo esc_html($this->lessonMeta($lesson)); ?></span>
                            </span>
                            <span class="am-course-lesson__arrow">
                                <?php echo CourseIcon::render(CourseIcon::ARROW_RIGHT); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                            </span>
                        </a>
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

    private function lessonUrl(string $coursePublicId, string $lessonPublicId): string
    {
        $value = rawurlencode($coursePublicId) . '/lekcja/' . rawurlencode($lessonPublicId);

        return wc_get_endpoint_url(self::ENDPOINT, $value, wc_get_page_permalink('myaccount'));
    }

    private function renderLesson(int $userId, string $coursePublicId, string $lessonPublicId): string
    {
        $lesson = $this->courses->lessonForUser($userId, $coursePublicId, $lessonPublicId);

        if (is_wp_error($lesson)) {
            $notFoundCodes = [
                'am_toolkit_course_not_available',
                'am_toolkit_course_lesson_not_available',
            ];
            status_header(in_array($lesson->get_error_code(), $notFoundCodes, true) ? 404 : 503);

            if ($lesson->get_error_code() === 'am_toolkit_course_lesson_not_available') {
                return $this->renderLessonError($lesson->get_error_message(), $coursePublicId);
            }

            return $this->renderError($lesson->get_error_message());
        }

        $course = isset($lesson['course']) && is_array($lesson['course']) ? $lesson['course'] : [];
        $materials = isset($lesson['materials']) && is_array($lesson['materials']) ? $lesson['materials'] : [];
        $navigation = isset($lesson['program_lessons']) && is_array($lesson['program_lessons'])
            ? $lesson['program_lessons']
            : [];
        $poster = !empty($course['image_attachment_id'])
            ? (string) wp_get_attachment_image_url((int) $course['image_attachment_id'], 'large')
            : '';
        $progress = $this->progress !== null
            ? $this->progress->lessonState($userId, $coursePublicId, $lessonPublicId)
            : null;

        ob_start();
        ?>
        <article class="am-lesson" aria-labelledby="am-lesson-title">
            <nav class="am-lesson__breadcrumbs" aria-label="<?php echo esc_attr__('Nawigacja kursu', 'am-toolkit'); ?>">
                <a href="<?php echo esc_url($this->courseUrl($coursePublicId)); ?>">
                    <?php echo CourseIcon::render(CourseIcon::ARROW_LEFT); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    <?php echo esc_html__('Wróć do programu', 'am-toolkit'); ?>
                </a>
            </nav>
            <div class="am-lesson__layout">
                <main class="am-lesson__main">
                    <header class="am-lesson__header">
                        <span class="am-courses__eyebrow"><?php echo esc_html((string) ($lesson['section_title'] ?? __('Lekcja kursu', 'am-toolkit'))); ?></span>
                        <h1 id="am-lesson-title"><?php echo esc_html((string) ($lesson['title'] ?? '')); ?></h1>
                        <p><?php echo esc_html($this->lessonMeta($lesson)); ?></p>
                    </header>

                    <?php echo $this->renderLessonVideo($lesson, $coursePublicId, $lessonPublicId, $poster); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    <?php if (is_array($progress)) : ?>
                        <?php echo $this->renderProgressPanel($progress, $coursePublicId, $lessonPublicId); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    <?php elseif (is_wp_error($progress)) : ?>
                        <p class="am-lesson-progress__notice" role="status"><?php echo esc_html__('Postęp jest chwilowo niedostępny, ale możesz korzystać z lekcji.', 'am-toolkit'); ?></p>
                    <?php endif; ?>

                    <?php if (!empty($lesson['description'])) : ?>
                        <section class="am-lesson__content" aria-labelledby="am-lesson-content-title">
                            <h2 id="am-lesson-content-title"><?php echo esc_html__('O tej lekcji', 'am-toolkit'); ?></h2>
                            <p><?php echo nl2br(esc_html((string) $lesson['description'])); ?></p>
                        </section>
                    <?php endif; ?>

                    <?php echo $this->renderMaterials($materials, $coursePublicId, $lessonPublicId); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    <?php echo $this->renderLessonNavigation($lesson, $coursePublicId); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                </main>
                <aside class="am-lesson__sidebar" aria-labelledby="am-lesson-program-title">
                    <span class="am-courses__eyebrow"><?php echo esc_html__('Twój kurs', 'am-toolkit'); ?></span>
                    <h2 id="am-lesson-program-title"><?php echo esc_html((string) ($course['title'] ?? __('Program kursu', 'am-toolkit'))); ?></h2>
                    <?php echo $this->renderCompactProgram($navigation, $coursePublicId, $lessonPublicId); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                </aside>
            </div>
        </article>
        <?php

        return (string) ob_get_clean();
    }

    /** @param array<string, mixed> $lesson */
    private function renderLessonVideo(
        array $lesson,
        string $coursePublicId,
        string $lessonPublicId,
        string $poster
    ): string {
        $provider = (string) ($lesson['video_provider'] ?? '');
        $reference = (string) ($lesson['video_reference'] ?? '');

        if ($provider === '' || $reference === '') {
            return $this->lessonState(
                __('Ta lekcja nie ma jeszcze nagrania', 'am-toolkit'),
                __('Możesz przejść do opisu i materiałów albo wybrać inną lekcję.', 'am-toolkit')
            );
        }

        $sourceUrl = $this->assets->url($coursePublicId, $lessonPublicId, 'video');
        $player = $this->videoRenderer->render($sourceUrl, ['poster' => $poster]);

        if (is_wp_error($player)) {
            return $this->lessonState(
                __('Nie udało się uruchomić nagrania', 'am-toolkit'),
                $player->get_error_message()
            );
        }

        return sprintf(
            '<section class="am-lesson-player" data-am-course-player data-course="%1$s" data-lesson="%2$s" aria-label="%3$s"><div class="am-course-player__loader" data-am-course-player-loader role="status" aria-label="%4$s">%5$s</div>%6$s<p class="am-lesson-player__status" data-am-course-player-status role="status" aria-live="polite"></p></section>',
            esc_attr($coursePublicId),
            esc_attr($lessonPublicId),
            esc_attr__('Nagranie lekcji', 'am-toolkit'),
            esc_attr__('Ładowanie nagrania', 'am-toolkit'),
            str_repeat('<span class="am-course-player__loader-dot" aria-hidden="true"></span>', 8),
            $player
        );
    }

    /** @param list<array<string, mixed>> $materials */
    private function renderMaterials(array $materials, string $coursePublicId, string $lessonPublicId): string
    {
        if ($materials === []) {
            return '';
        }

        ob_start();
        ?>
        <section class="am-lesson__materials" aria-labelledby="am-lesson-materials-title">
            <h2 id="am-lesson-materials-title"><?php echo esc_html__('Materiały do lekcji', 'am-toolkit'); ?></h2>
            <ul>
                <?php foreach ($materials as $material) : ?>
                    <li>
                        <div>
                            <strong><?php echo esc_html((string) ($material['name'] ?? '')); ?></strong>
                            <?php if (!empty($material['description'])) : ?><p><?php echo esc_html((string) $material['description']); ?></p><?php endif; ?>
                        </div>
                        <a href="<?php echo esc_url($this->assets->url($coursePublicId, $lessonPublicId, 'material', (string) ($material['public_id'] ?? ''))); ?>">
                            <?php echo esc_html__('Pobierz', 'am-toolkit'); ?>
                            <?php echo CourseIcon::render(CourseIcon::DOWNLOAD); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>
        <?php

        return (string) ob_get_clean();
    }

    /** @param array<string, mixed> $progress */
    private function renderProgressPanel(array $progress, string $coursePublicId, string $lessonPublicId): string
    {
        $completed = !empty($progress['lesson_completed']);
        $watched = min(100, max(0, (float) ($progress['watched_percent'] ?? 0)));
        $videoRequired = min(100, max(0, (int) ($progress['video_percent_required'] ?? 0)));
        $taskRequired = !empty($progress['task_required']);
        $taskCompleted = !empty($progress['task_completed']);

        ob_start();
        ?>
        <section
            class="am-lesson-progress<?php echo $completed ? ' am-lesson-progress--completed' : ''; ?>"
            data-am-course-progress
            data-course="<?php echo esc_attr($coursePublicId); ?>"
            data-lesson="<?php echo esc_attr($lessonPublicId); ?>"
            aria-labelledby="am-lesson-progress-title"
        >
            <header class="am-lesson-progress__header">
                <div>
                    <span class="am-courses__eyebrow"><?php echo esc_html__('Twój postęp', 'am-toolkit'); ?></span>
                    <h2 id="am-lesson-progress-title" data-am-progress-title>
                        <?php echo esc_html($completed ? __('Lekcja ukończona', 'am-toolkit') : __('Ukończ wymagania lekcji', 'am-toolkit')); ?>
                    </h2>
                </div>
                <span class="am-lesson-progress__badge" data-am-progress-badge>
                    <?php echo esc_html($completed ? '✓' : (string) ((int) ($progress['course_progress_percent'] ?? 0)) . '%'); ?>
                </span>
            </header>

            <?php if ($videoRequired > 0) : ?>
                <div class="am-lesson-progress__requirement" data-am-video-requirement>
                    <div class="am-lesson-progress__row">
                        <strong><?php echo esc_html__('Obejrzyj nagranie', 'am-toolkit'); ?></strong>
                        <span data-am-watched-label><?php echo esc_html(sprintf('%1$s%% / %2$d%%', $this->formatPercent($watched), $videoRequired)); ?></span>
                    </div>
                    <div class="am-lesson-progress__bar" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?php echo esc_attr((string) $watched); ?>">
                        <span data-am-watched-bar style="width: <?php echo esc_attr((string) $watched); ?>%"></span>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($taskRequired) : ?>
                <div class="am-lesson-progress__requirement am-lesson-progress__task" data-am-task-requirement>
                    <div>
                        <strong><?php echo esc_html__('Wykonaj zadanie z lekcji', 'am-toolkit'); ?></strong>
                        <p><?php echo esc_html__('Gdy zadanie jest gotowe, potwierdź jego wykonanie.', 'am-toolkit'); ?></p>
                    </div>
                    <button type="button" data-am-progress-action="acknowledge_task" <?php disabled($taskCompleted || $completed); ?>>
                        <?php echo esc_html($taskCompleted || $completed ? __('Zadanie wykonane', 'am-toolkit') : __('Potwierdzam wykonanie', 'am-toolkit')); ?>
                    </button>
                </div>
            <?php endif; ?>

            <?php if (!empty($progress['manual_completion_available']) && !$completed) : ?>
                <button class="am-lesson-progress__complete" type="button" data-am-progress-action="complete_manually">
                    <?php echo esc_html__('Oznacz jako ukończoną', 'am-toolkit'); ?>
                </button>
            <?php endif; ?>

            <p class="am-lesson-progress__message" data-am-progress-message role="status" aria-live="polite"></p>
        </section>
        <?php

        return (string) ob_get_clean();
    }

    private function formatPercent(float $percent): string
    {
        return rtrim(rtrim(number_format($percent, 1, ',', ''), '0'), ',');
    }

    /** @param array<string, mixed> $lesson */
    private function renderLessonNavigation(array $lesson, string $coursePublicId): string
    {
        $previous = isset($lesson['previous']) && is_array($lesson['previous']) ? $lesson['previous'] : null;
        $next = isset($lesson['next']) && is_array($lesson['next']) ? $lesson['next'] : null;

        if ($previous === null && $next === null) {
            return '';
        }

        ob_start();
        ?>
        <nav class="am-lesson__navigation" aria-label="<?php echo esc_attr__('Poprzednia i następna lekcja', 'am-toolkit'); ?>">
            <?php if ($previous !== null) : ?>
                <a href="<?php echo esc_url($this->lessonUrl($coursePublicId, (string) $previous['public_id'])); ?>"><small><?php echo esc_html__('Poprzednia lekcja', 'am-toolkit'); ?></small><strong><?php echo CourseIcon::render(CourseIcon::ARROW_LEFT); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php echo esc_html((string) $previous['title']); ?></strong></a>
            <?php else : ?><span></span><?php endif; ?>
            <?php if ($next !== null) : ?>
                <a class="am-lesson__navigation-next" href="<?php echo esc_url($this->lessonUrl($coursePublicId, (string) $next['public_id'])); ?>"><small><?php echo esc_html__('Następna lekcja', 'am-toolkit'); ?></small><strong><?php echo esc_html((string) $next['title']); ?><?php echo CourseIcon::render(CourseIcon::ARROW_RIGHT); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></strong></a>
            <?php endif; ?>
        </nav>
        <?php

        return (string) ob_get_clean();
    }

    /** @param list<array<string, mixed>> $lessons */
    private function renderCompactProgram(array $lessons, string $coursePublicId, string $currentLessonId): string
    {
        ob_start();
        ?>
        <ol class="am-lesson-program">
            <?php foreach ($lessons as $index => $item) : ?>
                <?php $isCurrent = (string) ($item['public_id'] ?? '') === $currentLessonId; ?>
                <li>
                    <a href="<?php echo esc_url($this->lessonUrl($coursePublicId, (string) ($item['public_id'] ?? ''))); ?>" <?php echo $isCurrent ? 'aria-current="page"' : ''; ?>>
                        <span><?php echo esc_html((string) ($index + 1)); ?></span>
                        <strong><?php echo esc_html((string) ($item['title'] ?? '')); ?></strong>
                    </a>
                </li>
            <?php endforeach; ?>
        </ol>
        <?php

        return (string) ob_get_clean();
    }

    private function lessonState(string $title, string $message): string
    {
        return sprintf(
            '<div class="am-lesson__state" role="status"><span aria-hidden="true">▷</span><div><h2>%1$s</h2><p>%2$s</p></div></div>',
            esc_html($title),
            esc_html($message)
        );
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

    private function renderLessonError(string $message, string $coursePublicId): string
    {
        return sprintf(
            '<div class="am-courses am-courses__error" role="alert"><h1>%1$s</h1><p>%2$s</p><a href="%3$s">%4$s</a></div>',
            esc_html__('Nie udało się otworzyć lekcji', 'am-toolkit'),
            esc_html($message),
            esc_url($this->courseUrl($coursePublicId)),
            esc_html__('Wróć do programu', 'am-toolkit')
        );
    }
}
