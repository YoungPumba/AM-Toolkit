<?php

namespace AMToolkit\Modules\Courses\Admin;

use AMToolkit\Core\Authorization;
use AMToolkit\Core\Capabilities;
use AMToolkit\Modules\Courses\Services\CourseAdminService;
use AMToolkit\Modules\Courses\Services\CourseDiagnosticsService;

defined('ABSPATH') || exit;

final class CourseDiagnosticsPage
{
    private const PAGE_SLUG = 'am-toolkit-course-diagnostics';
    private const PARENT_SLUG = 'am-toolkit-courses';
    private const NONCE_ACTION = 'am_toolkit_course_diagnostics';
    private const NONCE_NAME = 'am_toolkit_course_diagnostics_nonce';

    private string $hookSuffix = '';

    public function __construct(
        private CourseDiagnosticsService $diagnostics,
        private CourseAdminService $courses,
        private bool $repairEnabled
    ) {
    }

    public function boot(): void
    {
        add_action('admin_menu', [$this, 'registerMenu'], 20);
        add_action('admin_enqueue_scripts', [$this, 'enqueue']);
        add_action('admin_post_am_toolkit_course_diagnostics', [$this, 'handle']);
    }

    public function registerMenu(): void
    {
        $this->hookSuffix = (string) add_submenu_page(
            self::PARENT_SLUG,
            __('Diagnostyka kursów', 'am-toolkit'),
            __('Diagnostyka', 'am-toolkit'),
            Capabilities::VIEW_DIAGNOSTICS,
            self::PAGE_SLUG,
            [$this, 'render']
        );
    }

    public function enqueue(string $hookSuffix): void
    {
        if ($this->hookSuffix === '' || $this->hookSuffix !== $hookSuffix) {
            return;
        }

        wp_enqueue_style(
            'am-toolkit-course-diagnostics',
            AM_TOOLKIT_URL . 'assets/css/admin-course-diagnostics.css',
            [],
            AM_TOOLKIT_VERSION
        );
    }

    public function render(): void
    {
        if (!Authorization::canViewDiagnostics()) {
            wp_die(esc_html__('Nie masz uprawnień do diagnostyki kursów.', 'am-toolkit'));
        }

        $courses = $this->courses->courses();
        $health = $this->diagnostics->health();
        $userId = isset($_GET['user_id']) ? absint(wp_unslash($_GET['user_id'])) : 0;
        $courseId = isset($_GET['course_id']) ? absint(wp_unslash($_GET['course_id'])) : 0;
        $result = $userId > 0 && $courseId > 0
            ? $this->diagnostics->inspect($userId, $courseId)
            : null;
        ?>
        <div class="wrap am-toolkit-diagnostics">
            <h1><?php esc_html_e('Diagnostyka kursów', 'am-toolkit'); ?></h1>
            <p class="description">
                <?php esc_html_e('Sprawdź spójność dostępu i postępu bez zmieniania danych. Naprawa jest osobną, potwierdzaną operacją.', 'am-toolkit'); ?>
            </p>

            <?php $this->renderNotice(); ?>
            <?php $this->renderHealth($health); ?>

            <section class="am-toolkit-diagnostics__card">
                <h2><?php esc_html_e('1. Wybierz uczestniczkę i kurs', 'am-toolkit'); ?></h2>
                <form method="get" class="am-toolkit-diagnostics__filters">
                    <input type="hidden" name="page" value="<?php echo esc_attr(self::PAGE_SLUG); ?>">
                    <label>
                        <span><?php esc_html_e('ID użytkownika WordPress', 'am-toolkit'); ?></span>
                        <input type="number" min="1" name="user_id" value="<?php echo esc_attr((string) $userId); ?>" required>
                        <small><?php esc_html_e('ID znajdziesz w adresie strony profilu użytkownika w panelu WordPress.', 'am-toolkit'); ?></small>
                    </label>
                    <label>
                        <span><?php esc_html_e('Kurs', 'am-toolkit'); ?></span>
                        <select name="course_id" required>
                            <option value=""><?php esc_html_e('Wybierz kurs', 'am-toolkit'); ?></option>
                            <?php if (is_array($courses)) : ?>
                                <?php foreach ($courses as $course) : ?>
                                    <option value="<?php echo esc_attr((string) ($course['id'] ?? 0)); ?>" <?php selected($courseId, (int) ($course['id'] ?? 0)); ?>>
                                        <?php echo esc_html((string) ($course['title'] ?? '')); ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </label>
                    <button type="submit" class="button button-primary"><?php esc_html_e('Sprawdź', 'am-toolkit'); ?></button>
                </form>
                <?php if (is_wp_error($courses)) : ?>
                    <?php $this->renderError($courses); ?>
                <?php endif; ?>
            </section>

            <?php if (is_wp_error($result)) : ?>
                <?php $this->renderError($result); ?>
            <?php elseif (is_array($result)) : ?>
                <?php $this->renderResult($result, $userId, $courseId); ?>
            <?php endif; ?>
        </div>
        <?php
    }

    public function handle(): void
    {
        if (!Authorization::canViewDiagnostics()) {
            wp_die(esc_html__('Nie masz uprawnień do diagnostyki kursów.', 'am-toolkit'));
        }

        check_admin_referer(self::NONCE_ACTION, self::NONCE_NAME);
        $intent = isset($_POST['intent']) ? sanitize_key(wp_unslash($_POST['intent'])) : '';
        $userId = isset($_POST['user_id']) ? absint(wp_unslash($_POST['user_id'])) : 0;
        $courseId = isset($_POST['course_id']) ? absint(wp_unslash($_POST['course_id'])) : 0;

        if ($intent === 'export') {
            $this->export($userId, $courseId);
        }

        if ($intent !== 'repair') {
            wp_die(esc_html__('Nieprawidłowa operacja diagnostyczna.', 'am-toolkit'));
        }

        if (!Authorization::canRepairCourses() || !$this->repairEnabled) {
            wp_die(esc_html__('Narzędzia naprawcze są niedostępne.', 'am-toolkit'));
        }

        $confirmation = isset($_POST['confirmation'])
            ? strtoupper(trim(sanitize_text_field(wp_unslash($_POST['confirmation']))))
            : '';

        if ($confirmation !== 'PRZELICZ') {
            $this->redirect($userId, $courseId, 'confirmation_required');
        }

        $result = $this->diagnostics->repair($userId, $courseId);

        if (is_wp_error($result)) {
            $data = $result->get_error_data();
            $requestId = is_array($data) ? (string) ($data['request_id'] ?? '') : '';
            $this->redirect($userId, $courseId, 'repair_failed', $requestId, $result->get_error_code());
        }

        $this->redirect(
            $userId,
            $courseId,
            'repair_complete',
            (string) ($result['request_id'] ?? ''),
            '',
            (int) ($result['changed_aggregates'] ?? 0)
        );
    }

    /** @param array<string, mixed>|\WP_Error $health */
    private function renderHealth(array|\WP_Error $health): void
    {
        if (is_wp_error($health)) {
            $this->renderError($health);
            return;
        }

        $missing = array_keys(array_filter(
            (array) ($health['tables'] ?? []),
            static fn (bool $exists): bool => !$exists
        ));
        $orphans = array_filter(
            (array) ($health['orphan_counts'] ?? []),
            static fn (int $count): bool => $count > 0
        );
        ?>
        <section class="am-toolkit-diagnostics__card am-toolkit-diagnostics__health">
            <div>
                <span class="am-toolkit-diagnostics__eyebrow"><?php esc_html_e('Stan techniczny', 'am-toolkit'); ?></span>
                <h2><?php echo !empty($health['valid']) ? esc_html__('Schemat jest spójny', 'am-toolkit') : esc_html__('Schemat wymaga uwagi', 'am-toolkit'); ?></h2>
                <p>
                    <?php
                    echo esc_html(sprintf(
                        /* translators: 1: installed schema version, 2: expected schema version */
                        __('Wersja bazy: %1$d / oczekiwana: %2$d', 'am-toolkit'),
                        (int) ($health['installed_schema_version'] ?? 0),
                        (int) ($health['expected_schema_version'] ?? 0)
                    ));
                    ?>
                </p>
                <?php if ($missing !== []) : ?>
                    <p><strong><?php esc_html_e('Brakujące tabele:', 'am-toolkit'); ?></strong> <?php echo esc_html(implode(', ', $missing)); ?></p>
                <?php endif; ?>
                <?php if ($orphans !== []) : ?>
                    <p><strong><?php esc_html_e('Niespójne relacje:', 'am-toolkit'); ?></strong> <?php echo esc_html((string) array_sum($orphans)); ?></p>
                <?php endif; ?>
            </div>
            <span class="am-toolkit-diagnostics__status <?php echo !empty($health['valid']) ? 'is-good' : 'is-warning'; ?>">
                <?php echo !empty($health['valid']) ? esc_html__('OK', 'am-toolkit') : esc_html__('UWAGA', 'am-toolkit'); ?>
            </span>
        </section>
        <?php
    }

    /** @param array<string, mixed> $result */
    private function renderResult(array $result, int $userId, int $courseId): void
    {
        $snapshot = (array) ($result['snapshot'] ?? []);
        $course = (array) ($snapshot['course'] ?? []);
        $aggregate = (array) ($result['aggregate'] ?? []);
        $issues = (array) ($result['issues'] ?? []);
        ?>
        <section class="am-toolkit-diagnostics__card">
            <span class="am-toolkit-diagnostics__eyebrow"><?php esc_html_e('Wynik kontroli', 'am-toolkit'); ?></span>
            <h2><?php echo esc_html((string) ($course['title'] ?? '')); ?></h2>
            <div class="am-toolkit-diagnostics__metrics">
                <?php $this->metric(__('Aktywny dostęp', 'am-toolkit'), !empty($result['active_access']) ? __('Tak', 'am-toolkit') : __('Nie', 'am-toolkit')); ?>
                <?php $this->metric(__('Postęp kursu', 'am-toolkit'), (int) ($aggregate['expected_progress_percent'] ?? 0) . '%'); ?>
                <?php $this->metric(__('Lekcje wymagane', 'am-toolkit'), (int) ($aggregate['completed_required_lessons'] ?? 0) . ' / ' . (int) ($aggregate['required_lessons'] ?? 0)); ?>
                <?php $this->metric(__('Ukończenie zapisane', 'am-toolkit'), !empty($aggregate['completion_recorded']) ? __('Tak', 'am-toolkit') : __('Nie', 'am-toolkit')); ?>
            </div>
        </section>

        <section class="am-toolkit-diagnostics__card">
            <h2><?php esc_html_e('Dostęp i ostatnia aktywność', 'am-toolkit'); ?></h2>
            <div class="am-toolkit-diagnostics__metrics">
                <?php $this->metric(__('Ostatnio otwarta', 'am-toolkit'), (string) ($snapshot['last_opened_lesson']['title'] ?? __('Brak', 'am-toolkit'))); ?>
                <?php $this->metric(__('Ostatnio ukończona', 'am-toolkit'), (string) ($snapshot['last_completed_lesson']['title'] ?? __('Brak', 'am-toolkit'))); ?>
                <?php $this->metric(__('Wersja programu', 'am-toolkit'), (string) ($snapshot['program']['version_number'] ?? __('Brak', 'am-toolkit'))); ?>
                <?php $this->metric(__('Wersja AM Toolkit', 'am-toolkit'), defined('AM_TOOLKIT_VERSION') ? AM_TOOLKIT_VERSION : __('Nieznana', 'am-toolkit')); ?>
            </div>
            <?php if (!empty($snapshot['grants'])) : ?>
                <table class="widefat striped am-toolkit-diagnostics__table">
                    <thead><tr><th><?php esc_html_e('Źródło', 'am-toolkit'); ?></th><th><?php esc_html_e('Status', 'am-toolkit'); ?></th><th><?php esc_html_e('Od', 'am-toolkit'); ?></th><th><?php esc_html_e('Do', 'am-toolkit'); ?></th></tr></thead>
                    <tbody>
                    <?php foreach ((array) $snapshot['grants'] as $grant) : ?>
                        <tr>
                            <td><?php echo esc_html((string) ($grant['source_type'] ?? '')); ?> #<?php echo esc_html((string) ($grant['source_id'] ?? '')); ?></td>
                            <td><?php echo esc_html((string) ($grant['status'] ?? '')); ?><?php echo !empty($grant['is_active']) ? ' · ' . esc_html__('aktywny', 'am-toolkit') : ''; ?></td>
                            <td><?php echo esc_html((string) ($grant['starts_at'] ?? '—') ?: '—'); ?></td>
                            <td><?php echo esc_html((string) ($grant['expires_at'] ?? '—') ?: '—'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </section>

        <section class="am-toolkit-diagnostics__card">
            <h2><?php esc_html_e('Lekcje: zapis a wynik ze źródeł', 'am-toolkit'); ?></h2>
            <table class="widefat striped am-toolkit-diagnostics__table">
                <thead><tr><th><?php esc_html_e('Lekcja', 'am-toolkit'); ?></th><th><?php esc_html_e('Zapisany stan', 'am-toolkit'); ?></th><th><?php esc_html_e('Wersja', 'am-toolkit'); ?></th><th><?php esc_html_e('Wynik źródeł', 'am-toolkit'); ?></th></tr></thead>
                <tbody>
                <?php foreach ((array) ($snapshot['lessons'] ?? []) as $lesson) : ?>
                    <?php $sourceState = (array) ($result['lesson_states'][(int) ($lesson['id'] ?? 0)] ?? []); ?>
                    <tr>
                        <td><?php echo esc_html((string) ($lesson['title'] ?? '')); ?><?php echo !empty($lesson['is_required']) ? ' · ' . esc_html__('wymagana', 'am-toolkit') : ''; ?></td>
                        <td><?php echo esc_html((string) ($lesson['progress_status'] ?? __('brak', 'am-toolkit')) ?: __('brak', 'am-toolkit')); ?></td>
                        <td><?php echo esc_html((string) ($lesson['progress_content_version'] ?? 0)); ?> / <?php echo esc_html((string) ($lesson['content_version'] ?? 0)); ?></td>
                        <td>
                            <?php if (isset($sourceState['error_code'])) : ?>
                                <code><?php echo esc_html((string) $sourceState['error_code']); ?></code>
                            <?php else : ?>
                                <?php echo esc_html((string) ($sourceState['lesson_progress_percent'] ?? 0)); ?>% · <?php echo esc_html((string) ($sourceState['status'] ?? __('brak', 'am-toolkit'))); ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </section>

        <section class="am-toolkit-diagnostics__card">
            <h2><?php esc_html_e('Wykryte problemy', 'am-toolkit'); ?></h2>
            <?php if ($issues === []) : ?>
                <p class="am-toolkit-diagnostics__empty"><?php esc_html_e('Nie wykryto sprzeczności dla wybranego użytkownika i kursu.', 'am-toolkit'); ?></p>
            <?php else : ?>
                <ul class="am-toolkit-diagnostics__issues">
                    <?php foreach ($issues as $issue) : ?>
                        <li class="is-<?php echo esc_attr((string) ($issue['severity'] ?? 'warning')); ?>">
                            <strong><?php echo esc_html((string) ($issue['code'] ?? '')); ?></strong>
                            <span><?php echo esc_html((string) ($issue['message'] ?? '')); ?></span>
                            <?php if (!empty($issue['repairable'])) : ?>
                                <em><?php esc_html_e('Możliwe do przeliczenia', 'am-toolkit'); ?></em>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>

        <section class="am-toolkit-diagnostics__card">
            <h2><?php esc_html_e('Ostatnie zdarzenia', 'am-toolkit'); ?></h2>
            <?php if (empty($result['events'])) : ?>
                <p class="am-toolkit-diagnostics__empty"><?php esc_html_e('Brak zdarzeń kursu w ostatnich 50 zdarzeniach użytkownika.', 'am-toolkit'); ?></p>
            <?php else : ?>
                <table class="widefat striped">
                    <thead><tr><th><?php esc_html_e('Czas UTC', 'am-toolkit'); ?></th><th><?php esc_html_e('Zdarzenie', 'am-toolkit'); ?></th><th><?php esc_html_e('Request ID', 'am-toolkit'); ?></th></tr></thead>
                    <tbody>
                    <?php foreach ((array) $result['events'] as $event) : ?>
                        <tr>
                            <td><?php echo esc_html((string) ($event['occurred_at'] ?? '')); ?></td>
                            <td><?php echo esc_html((string) ($event['event_type'] ?? '')); ?></td>
                            <td><code><?php echo esc_html((string) ($event['request_id'] ?? '')); ?></code></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </section>

        <section class="am-toolkit-diagnostics__card am-toolkit-diagnostics__actions">
            <div>
                <h2><?php esc_html_e('Eksport diagnostyczny', 'am-toolkit'); ?></h2>
                <p><?php esc_html_e('Pobiera JSON bez adresu e-mail, pełnych danych osobowych, tokenów i prywatnych odnośników.', 'am-toolkit'); ?></p>
                <?php $this->actionForm('export', $userId, $courseId); ?>
            </div>
            <div>
                <h2><?php esc_html_e('Bezpieczne przeliczenie', 'am-toolkit'); ?></h2>
                <?php if (!$this->repairEnabled) : ?>
                    <p><?php esc_html_e('Narzędzia naprawcze są wyłączone przełącznikiem awaryjnym.', 'am-toolkit'); ?></p>
                <?php elseif (!Authorization::canRepairCourses()) : ?>
                    <p><?php esc_html_e('Masz dostęp tylko do diagnostyki. Naprawa wymaga uprawnienia administratora.', 'am-toolkit'); ?></p>
                <?php elseif (empty($result['repair_preview']['available'])) : ?>
                    <p><?php esc_html_e('Przeliczenie jest zablokowane, dopóki dostęp, użytkownik i opublikowany program nie są prawidłowe.', 'am-toolkit'); ?></p>
                <?php else : ?>
                    <p><?php echo esc_html(sprintf(
                        /* translators: %d: number of lessons */
                        __('Operacja ponownie odczyta źródła postępu dla %d lekcji. Jest idempotentna i zostanie zapisana w audycie.', 'am-toolkit'),
                        (int) ($result['repair_preview']['lesson_count'] ?? 0)
                    )); ?></p>
                    <?php $this->repairForm($userId, $courseId); ?>
                <?php endif; ?>
            </div>
        </section>
        <?php
    }

    private function actionForm(string $intent, int $userId, int $courseId): void
    {
        ?>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <input type="hidden" name="action" value="am_toolkit_course_diagnostics">
            <input type="hidden" name="intent" value="<?php echo esc_attr($intent); ?>">
            <input type="hidden" name="user_id" value="<?php echo esc_attr((string) $userId); ?>">
            <input type="hidden" name="course_id" value="<?php echo esc_attr((string) $courseId); ?>">
            <?php wp_nonce_field(self::NONCE_ACTION, self::NONCE_NAME); ?>
            <button type="submit" class="button"><?php esc_html_e('Pobierz bezpieczny JSON', 'am-toolkit'); ?></button>
        </form>
        <?php
    }

    private function repairForm(int $userId, int $courseId): void
    {
        ?>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="am-toolkit-diagnostics__repair">
            <input type="hidden" name="action" value="am_toolkit_course_diagnostics">
            <input type="hidden" name="intent" value="repair">
            <input type="hidden" name="user_id" value="<?php echo esc_attr((string) $userId); ?>">
            <input type="hidden" name="course_id" value="<?php echo esc_attr((string) $courseId); ?>">
            <?php wp_nonce_field(self::NONCE_ACTION, self::NONCE_NAME); ?>
            <label>
                <span><?php esc_html_e('Wpisz PRZELICZ, aby potwierdzić', 'am-toolkit'); ?></span>
                <input type="text" name="confirmation" autocomplete="off" required>
            </label>
            <button type="submit" class="button button-primary"><?php esc_html_e('Przelicz postęp', 'am-toolkit'); ?></button>
        </form>
        <?php
    }

    private function export(int $userId, int $courseId): void
    {
        $json = $this->diagnostics->export($userId, $courseId);

        if (is_wp_error($json)) {
            wp_die(esc_html($json->get_error_message()));
        }

        nocache_headers();
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="am-toolkit-course-diagnostics-' . $courseId . '.json"');
        echo $json; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON download generated by the diagnostics service.
        exit;
    }

    private function redirect(
        int $userId,
        int $courseId,
        string $notice,
        string $requestId = '',
        string $errorCode = '',
        int $changed = 0
    ): void {
        wp_safe_redirect(add_query_arg([
            'page' => self::PAGE_SLUG,
            'user_id' => $userId,
            'course_id' => $courseId,
            'diagnostic_notice' => sanitize_key($notice),
            'request_id' => sanitize_text_field($requestId),
            'error_code' => sanitize_key($errorCode),
            'changed' => max(0, $changed),
        ], admin_url('admin.php')));
        exit;
    }

    private function renderNotice(): void
    {
        $notice = isset($_GET['diagnostic_notice']) ? sanitize_key(wp_unslash($_GET['diagnostic_notice'])) : '';

        if ($notice === '') {
            return;
        }

        $requestId = isset($_GET['request_id']) ? sanitize_text_field(wp_unslash($_GET['request_id'])) : '';
        $errorCode = isset($_GET['error_code']) ? sanitize_key(wp_unslash($_GET['error_code'])) : '';
        $changed = isset($_GET['changed']) ? absint(wp_unslash($_GET['changed'])) : 0;
        $class = $notice === 'repair_complete' ? 'notice-success' : 'notice-error';
        $message = match ($notice) {
            'repair_complete' => sprintf(
                /* translators: %d: number of changed aggregates */
                __('Przeliczenie zakończone. Zmienione agregaty: %d.', 'am-toolkit'),
                $changed
            ),
            'confirmation_required' => __('Wpisz dokładnie PRZELICZ, aby uruchomić naprawę.', 'am-toolkit'),
            default => sprintf(
                /* translators: %s: technical error code */
                __('Przeliczenie nie powiodło się. Kod: %s. Możesz bezpiecznie spróbować ponownie.', 'am-toolkit'),
                $errorCode !== '' ? $errorCode : 'unknown'
            ),
        };
        ?>
        <div class="notice <?php echo esc_attr($class); ?> is-dismissible">
            <p><?php echo esc_html($message); ?></p>
            <?php if ($requestId !== '') : ?><p><code><?php echo esc_html($requestId); ?></code></p><?php endif; ?>
        </div>
        <?php
    }

    private function metric(string $label, string $value): void
    {
        ?><div><span><?php echo esc_html($label); ?></span><strong><?php echo esc_html($value); ?></strong></div><?php
    }

    private function renderError(\WP_Error $error): void
    {
        ?>
        <div class="notice notice-error"><p><?php echo esc_html($error->get_error_message()); ?> <code><?php echo esc_html($error->get_error_code()); ?></code></p></div>
        <?php
    }
}
