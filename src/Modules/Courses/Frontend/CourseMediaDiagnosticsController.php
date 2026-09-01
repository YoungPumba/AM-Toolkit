<?php

namespace AMToolkit\Modules\Courses\Frontend;

use AMToolkit\Modules\Courses\Services\CourseCatalogService;
use AMToolkit\Modules\Courses\Services\CourseMediaDiagnosticsService;

defined('ABSPATH') || exit;

final class CourseMediaDiagnosticsController
{
    public const ACTION = 'am_toolkit_course_media_diagnostics';

    private const NONCE_ACTION = 'am_toolkit_course_media_diagnostics';

    public function __construct(
        private CourseCatalogService $courses,
        private CourseMediaDiagnosticsService $diagnostics
    ) {
    }

    public function boot(): void
    {
        add_action('wp_ajax_' . self::ACTION, [$this, 'handle']);
    }

    public function nonce(): string
    {
        return wp_create_nonce(self::NONCE_ACTION);
    }

    public function createSessionId(): string
    {
        return $this->diagnostics->createSessionId();
    }

    public function isRequested(): bool
    {
        if (
            !isset($_GET[CourseMediaDiagnosticsService::QUERY_FLAG]) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            || !is_scalar($_GET[CourseMediaDiagnosticsService::QUERY_FLAG]) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        ) {
            return false;
        }

        return sanitize_key((string) wp_unslash($_GET[CourseMediaDiagnosticsService::QUERY_FLAG])) === '1'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    }

    public function handle(): void
    {
        if (!is_user_logged_in() || !wp_verify_nonce($this->postValue('nonce'), self::NONCE_ACTION)) {
            wp_send_json_error([
                'message' => __('Sesja diagnostyczna wygasła. Odśwież stronę i spróbuj ponownie.', 'am-toolkit'),
            ], 403);
        }

        $course = $this->postValue('course');
        $lesson = $this->postValue('lesson');
        $session = $this->postValue('diagnostic_session');
        $userId = get_current_user_id();
        $lessonData = $this->courses->lessonForUser($userId, $course, $lesson);

        if (is_wp_error($lessonData) || !$this->diagnostics->isValidSessionId($session)) {
            wp_send_json_error([
                'message' => __('Nie można utworzyć raportu dla tej lekcji.', 'am-toolkit'),
            ], 403);
        }

        $events = json_decode($this->rawPostValue('events'), true);
        $environment = json_decode($this->rawPostValue('environment'), true);
        $report = $this->diagnostics->report(
            $userId,
            $session,
            $course,
            $lesson,
            is_array($events) ? array_values($events) : [],
            is_array($environment) ? $environment : [],
            defined('AM_TOOLKIT_VERSION') ? AM_TOOLKIT_VERSION : ''
        );

        wp_send_json_success(['report' => $report]);
    }

    private function postValue(string $key): string
    {
        return sanitize_text_field($this->rawPostValue($key));
    }

    private function rawPostValue(string $key): string
    {
        if (!isset($_POST[$key]) || !is_scalar($_POST[$key])) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
            return '';
        }

        return (string) wp_unslash($_POST[$key]); // phpcs:ignore WordPress.Security.NonceVerification.Missing
    }
}
