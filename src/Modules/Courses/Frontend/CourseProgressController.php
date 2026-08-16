<?php

namespace AMToolkit\Modules\Courses\Frontend;

use AMToolkit\Modules\Courses\Services\CourseProgressService;

defined('ABSPATH') || exit;

final class CourseProgressController
{
    public const ACTION = 'am_toolkit_course_progress';

    private const NONCE_ACTION = 'am_toolkit_course_progress';

    public function __construct(private CourseProgressService $progress)
    {
    }

    public function boot(): void
    {
        add_action('wp_ajax_' . self::ACTION, [$this, 'handle']);
    }

    public function nonce(): string
    {
        return wp_create_nonce(self::NONCE_ACTION);
    }

    public function handle(): void
    {
        if (!is_user_logged_in() || !wp_verify_nonce($this->postValue('nonce'), self::NONCE_ACTION)) {
            wp_send_json_error([
                'message' => __('Sesja wygasła. Odśwież stronę i spróbuj ponownie.', 'am-toolkit'),
            ], 403);
        }

        $operation = sanitize_key($this->postValue('operation'));
        $course = $this->postValue('course');
        $lesson = $this->postValue('lesson');
        $requestId = $this->postValue('request_id');
        $userId = get_current_user_id();

        if ($operation === 'video_checkpoint') {
            $decoded = json_decode($this->postValue('intervals'), true);
            $intervals = is_array($decoded) ? $decoded : [];
            $result = $this->progress->recordVideoCheckpoint(
                $userId,
                $course,
                $lesson,
                $intervals,
                $requestId
            );
        } elseif ($operation === 'acknowledge_task') {
            $result = $this->progress->acknowledgeTask($userId, $course, $lesson, $requestId);
        } elseif ($operation === 'set_lesson_task') {
            $result = $this->progress->setLessonTask(
                $userId,
                $course,
                $lesson,
                $this->postValue('task'),
                $this->postValue('completed') === '1',
                $requestId
            );
        } elseif ($operation === 'complete_manually') {
            $result = $this->progress->completeManually($userId, $course, $lesson, $requestId);
        } else {
            $result = new \WP_Error(
                'am_toolkit_course_progress_unknown_operation',
                __('Nieznana operacja postępu kursu.', 'am-toolkit')
            );
        }

        if (is_wp_error($result)) {
            wp_send_json_error([
                'code' => $result->get_error_code(),
                'message' => $result->get_error_message(),
            ], 400);
        }

        wp_send_json_success(['progress' => $result]);
    }

    private function postValue(string $key): string
    {
        if (!isset($_POST[$key]) || !is_scalar($_POST[$key])) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
            return '';
        }

        return sanitize_text_field((string) wp_unslash($_POST[$key])); // phpcs:ignore WordPress.Security.NonceVerification.Missing
    }
}
