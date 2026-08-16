<?php

namespace AMToolkit\Modules\Courses\Admin;

use AMToolkit\Core\Authorization;
use AMToolkit\Core\Capabilities;
use AMToolkit\Modules\Courses\Contracts\CourseAssetStore;
use AMToolkit\Modules\Courses\Domain\PublicationStatus;
use AMToolkit\Modules\Courses\Services\AccessCoreCourseEntitlementGateway;
use AMToolkit\Modules\Courses\Services\CourseAccessLifecycle;
use AMToolkit\Modules\Courses\Services\CourseAdminService;
use AMToolkit\Modules\Courses\Services\CourseMeetingService;
use AMToolkit\Modules\Courses\Services\CourseQaService;
use AMToolkit\Modules\Courses\Domain\MeetingStatus;
use AMToolkit\Modules\Courses\WpdbCourseAdminStore;
use AMToolkit\Modules\Courses\WpdbProductCourseMappingStore;
use AMToolkit\Modules\Courses\WpPrivateCourseAssetStore;

defined('ABSPATH') || exit;

final class CourseAdminPage
{
    private const PAGE_SLUG = 'am-toolkit-courses';
    private const NONCE_ACTION = 'am_toolkit_courses_admin';
    private const NONCE_NAME = 'am_toolkit_courses_nonce';

    private CourseAdminService $courses;
    private CourseAssetStore $assets;
    private ?CourseMeetingService $meetings;
    private ?CourseQaService $qa;

    public function __construct(
        ?CourseAdminService $courses = null,
        ?CourseAssetStore $assets = null,
        ?CourseMeetingService $meetings = null,
        ?CourseQaService $qa = null
    )
    {
        $this->assets = $assets ?? new WpPrivateCourseAssetStore();
        $this->meetings = $meetings;
        $this->qa = $qa;

        if ($courses !== null) {
            $this->courses = $courses;
            return;
        }

        $mappings = new WpdbProductCourseMappingStore();
        $this->courses = new CourseAdminService(
            new WpdbCourseAdminStore(),
            $mappings,
            new CourseAccessLifecycle(
                $mappings,
                new AccessCoreCourseEntitlementGateway()
            )
        );
    }

    public function boot(): void
    {
        add_action('admin_menu', [$this, 'registerMenu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue']);
        add_action('admin_post_am_toolkit_courses_admin', [$this, 'handle']);
    }

    public function registerMenu(): void
    {
        add_menu_page(
            __('AM Courses', 'am-toolkit'),
            __('Kursy', 'am-toolkit'),
            Capabilities::MANAGE_COURSES,
            self::PAGE_SLUG,
            [$this, 'render'],
            'dashicons-welcome-learn-more',
            59
        );
    }

    public function enqueue(string $hookSuffix): void
    {
        if ('toplevel_page_' . self::PAGE_SLUG !== $hookSuffix) {
            return;
        }

        wp_enqueue_style(
            'am-toolkit-courses-admin',
            AM_TOOLKIT_URL . 'assets/css/admin-courses.css',
            [],
            AM_TOOLKIT_VERSION
        );
    }

    public function handle(): void
    {
        if (!Authorization::canManageCourses()) {
            wp_die(esc_html__('Nie masz uprawnień do zarządzania kursami.', 'am-toolkit'));
        }

        check_admin_referer(self::NONCE_ACTION, self::NONCE_NAME);
        $intent = $this->postKey('intent');
        $courseId = $this->postInt('course_id');

        switch ($intent) {
            case 'save_course':
                $result = $this->courses->saveCourse(
                    $courseId,
                    $this->postText('title'),
                    $this->postTextarea('description'),
                    $this->postInt('image_attachment_id'),
                    $this->postStatus()
                );
                $courseId = is_wp_error($result) ? $courseId : (int) $result;
                break;

            case 'archive_course':
                $result = $this->courses->archiveCourse($courseId);
                break;

            case 'save_section':
                $result = $this->courses->saveSection(
                    $this->postInt('section_id'),
                    $courseId,
                    $this->postText('title'),
                    $this->postTextarea('description'),
                    $this->postInt('position'),
                    $this->postStatus()
                );
                break;

            case 'archive_section':
                $result = $this->courses->archiveSection($this->postInt('section_id'), $courseId);
                break;

            case 'save_lesson':
                $sectionId = $this->postInt('section_id');
                $duration = $this->postOptionalInt('duration_seconds');
                $videoProvider = $this->postKey('video_provider');
                $videoReference = $this->postText('video_reference');
                $previousVideoProvider = $videoProvider;
                $previousVideoReference = $videoReference;
                $uploadedReference = null;
                $videoUpload = $this->uploadedFile('video_file');

                if ($videoUpload !== null) {
                    $uploadedReference = $this->assets->storeUpload($videoUpload, 'video');

                    if (is_wp_error($uploadedReference)) {
                        $result = $uploadedReference;
                        break;
                    }

                    $videoProvider = $this->assets->provider();
                    $videoReference = $uploadedReference;
                }

                if ($videoProvider === $this->assets->provider() && $videoReference !== '') {
                    $detectedDuration = $this->assets->videoDurationSeconds($videoReference);

                    if (!is_wp_error($detectedDuration)) {
                        $duration = $detectedDuration;
                    }
                }

                $result = $this->courses->saveLesson(
                    $this->postInt('lesson_id'),
                    $courseId,
                    $sectionId > 0 ? $sectionId : null,
                    $this->postText('title'),
                    $this->postTextarea('description'),
                    $videoProvider,
                    $videoReference,
                    $duration,
                    [
                        'video_percent' => min(100, $this->postInt('video_percent')),
                        'task_required' => $this->postBool('task_required'),
                    ],
                    $this->postInt('position'),
                    $this->postBool('is_required'),
                    $this->postStatus()
                );

                if (is_wp_error($result) && is_string($uploadedReference)) {
                    $this->assets->remove($uploadedReference);
                } elseif (
                    !is_wp_error($result)
                    && is_string($uploadedReference)
                    && $previousVideoProvider === $this->assets->provider()
                    && $previousVideoReference !== ''
                    && $previousVideoReference !== $uploadedReference
                ) {
                    $this->assets->remove($previousVideoReference);
                }
                break;

            case 'archive_lesson':
                $result = $this->courses->archiveLesson($this->postInt('lesson_id'), $courseId);
                break;

            case 'save_material':
                $storageProvider = $this->postKey('storage_provider');
                $storageReference = $this->postText('storage_reference');
                $previousStorageProvider = $storageProvider;
                $previousStorageReference = $storageReference;
                $uploadedReference = null;
                $materialUpload = $this->uploadedFile('material_file');

                if ($materialUpload !== null) {
                    $uploadedReference = $this->assets->storeUpload($materialUpload, 'material');

                    if (is_wp_error($uploadedReference)) {
                        $result = $uploadedReference;
                        break;
                    }

                    $storageProvider = $this->assets->provider();
                    $storageReference = $uploadedReference;
                }

                $result = $this->courses->saveMaterial(
                    $this->postInt('material_id'),
                    $this->postInt('lesson_id'),
                    $this->postText('name'),
                    $this->postTextarea('description'),
                    $storageProvider,
                    $storageReference,
                    $this->postInt('position'),
                    $this->postStatus()
                );

                if (is_wp_error($result) && is_string($uploadedReference)) {
                    $this->assets->remove($uploadedReference);
                } elseif (
                    !is_wp_error($result)
                    && is_string($uploadedReference)
                    && $previousStorageProvider === $this->assets->provider()
                    && $previousStorageReference !== ''
                    && $previousStorageReference !== $uploadedReference
                ) {
                    $this->assets->remove($previousStorageReference);
                }
                break;

            case 'archive_material':
                $result = $this->courses->archiveMaterial(
                    $this->postInt('material_id'),
                    $this->postInt('lesson_id')
                );
                break;

            case 'save_mappings':
                $submitted = isset($_POST['product_ids'])
                    ? (array) wp_unslash($_POST['product_ids'])
                    : [];
                $submitted = array_values(array_filter($submitted, 'is_scalar'));
                $result = $this->courses->replaceProductMappings(
                    $courseId,
                    array_map('absint', $submitted)
                );
                break;

            case 'save_course_links':
                $result = $this->meetings !== null
                    ? $this->meetings->saveTelegram(
                        $courseId,
                        $this->postUrl('telegram_reference'),
                        get_current_user_id()
                    )
                    : new \WP_Error('am_toolkit_course_meetings_disabled', __('Informacje organizacyjne są wyłączone.', 'am-toolkit'));
                break;

            case 'save_meeting':
                $result = $this->meetings !== null
                    ? $this->meetings->saveMeeting([
                        'id' => $this->postInt('meeting_id'),
                        'course_id' => $courseId,
                        'title' => $this->postText('title'),
                        'description' => $this->postTextarea('description'),
                        'starts_at' => $this->postValue('starts_at'),
                        'ends_at' => $this->postValue('ends_at'),
                        'display_timezone' => $this->postText('display_timezone'),
                        'platform' => $this->postText('platform'),
                        'location' => $this->postText('location'),
                        'join_reference' => $this->postUrl('join_reference'),
                        'recording_reference' => $this->postUrl('recording_reference'),
                        'status' => $this->postKey('meeting_status'),
                    ], get_current_user_id())
                    : new \WP_Error('am_toolkit_course_meetings_disabled', __('Informacje organizacyjne są wyłączone.', 'am-toolkit'));
                break;

            case 'save_qa':
                $result = $this->qa !== null
                    ? $this->qa->save([
                        'id' => $this->postInt('qa_entry_id'),
                        'public_id' => $this->postText('public_id'),
                        'course_id' => $courseId,
                        'lesson_id' => $this->postInt('lesson_id'),
                        'question' => $this->postTextarea('question'),
                        'answer' => $this->postTextarea('answer'),
                        'position' => $this->postInt('position'),
                        'status' => $this->postStatus(),
                    ], get_current_user_id())
                    : new \WP_Error('am_toolkit_course_qa_disabled', __('Sekcja Q&A jest wyłączona.', 'am-toolkit'));
                break;

            case 'archive_qa':
                $result = $this->qa !== null
                    ? $this->qa->archive(
                        $this->postInt('qa_entry_id'),
                        $courseId,
                        get_current_user_id()
                    )
                    : new \WP_Error('am_toolkit_course_qa_disabled', __('Sekcja Q&A jest wyłączona.', 'am-toolkit'));
                break;

            case 'grant_manual':
                if (!Authorization::canManageAccess()) {
                    wp_die(esc_html__('Nie masz uprawnień do zarządzania dostępem.', 'am-toolkit'));
                }
                $result = $this->courses->grantManual(
                    $this->postInt('user_id'),
                    $courseId,
                    random_int(1, 2147483647)
                );
                break;

            case 'revoke_manual':
                if (!Authorization::canManageAccess()) {
                    wp_die(esc_html__('Nie masz uprawnień do zarządzania dostępem.', 'am-toolkit'));
                }
                $result = $this->courses->revokeManual($this->postInt('assignment_id'));
                break;

            default:
                $result = new \WP_Error(
                    'am_toolkit_course_admin_unknown_action',
                    __('Nieznana operacja panelu kursów.', 'am-toolkit')
                );
        }

        $this->redirect($courseId, is_wp_error($result) ? $result->get_error_code() : 'saved');
    }

    public function render(): void
    {
        if (!Authorization::canManageCourses()) {
            wp_die(esc_html__('Nie masz uprawnień do zarządzania kursami.', 'am-toolkit'));
        }

        $courseId = absint($this->queryValue('course_id'));
        $courses = $this->valueOrEmpty($this->courses->courses());
        $course = $courseId > 0 ? $this->courses->course($courseId) : null;

        if (is_wp_error($course)) {
            $course = null;
        }

        $this->renderHeader($courseId, $course);
        $this->renderNotice();

        if ($courseId === 0 || !is_array($course)) {
            $this->renderCourseList($courses);
            $this->renderCourseForm(null);
            echo '</div>';
            return;
        }

        $sections = $this->valueOrEmpty($this->courses->sections($courseId));
        $lessons = $this->valueOrEmpty($this->courses->lessons($courseId));
        $materials = $this->valueOrEmpty($this->courses->materials($courseId));
        $productIds = $this->valueOrEmpty($this->courses->productIds($courseId));
        $participants = $this->valueOrEmpty($this->courses->participants($courseId));
        $activity = $this->valueOrEmpty($this->courses->activity($courseId));
        $meetings = $this->meetings !== null ? $this->valueOrEmpty($this->meetings->meetings($courseId)) : [];
        $meetingSettings = $this->meetings !== null ? $this->meetings->courseSettings($courseId) : null;
        $meetingSettings = is_array($meetingSettings) ? $meetingSettings : [];
        $qaEntries = $this->qa !== null ? $this->valueOrEmpty($this->qa->entries($courseId)) : [];

        echo '<div class="amt-courses-layout">';
        echo '<main class="amt-courses-main">';
        $this->renderCourseForm($course);
        $this->renderSections($courseId, $sections);
        $this->renderLessons($courseId, $sections, $lessons);
        if ($this->qa !== null) {
            $this->renderQa($courseId, $lessons, $qaEntries);
        }
        $this->renderMaterials($courseId, $lessons, $materials);
        if ($this->meetings !== null) {
            $this->renderMeetings($courseId, $meetings, $meetingSettings);
        }
        echo '</main><aside class="amt-courses-side">';
        $this->renderMappings($courseId, $productIds);
        $this->renderAccess($courseId, $participants, $activity);
        echo '</aside></div></div>';
    }

    /** @param array<string, mixed>|null $course */
    private function renderHeader(int $courseId, ?array $course): void
    {
        $title = $courseId > 0 && is_array($course)
            ? (string) ($course['title'] ?? __('Kurs', 'am-toolkit'))
            : __('Panel kursów', 'am-toolkit');
        ?>
        <div class="wrap amt-courses-admin">
            <header class="amt-courses-header">
                <div>
                    <p class="amt-courses-eyebrow"><?php esc_html_e('AM Toolkit · Courses', 'am-toolkit'); ?></p>
                    <h1><?php echo esc_html($title); ?></h1>
                    <p><?php esc_html_e('Zarządzaj programem, materiałami i dostępem bez usuwania historii.', 'am-toolkit'); ?></p>
                </div>
                <div class="amt-courses-header__actions">
                    <?php if ($courseId > 0) : ?>
                        <a class="button" href="<?php echo esc_url($this->pageUrl()); ?>"><?php esc_html_e('Wszystkie kursy', 'am-toolkit'); ?></a>
                    <?php endif; ?>
                    <span class="amt-courses-version">v<?php echo esc_html(AM_TOOLKIT_VERSION); ?></span>
                </div>
            </header>
        <?php
    }

    private function renderNotice(): void
    {
        $notice = sanitize_key($this->queryValue('amt_notice'));

        if ($notice === '') {
            return;
        }

        $success = $notice === 'saved';
        $message = $success
            ? __('Zmiany zostały zapisane.', 'am-toolkit')
            : __('Nie udało się zapisać zmian. Sprawdź dane i spróbuj ponownie.', 'am-toolkit');
        if ($success) {
            printf('<div class="notice notice-success is-dismissible"><p>%s</p></div>', esc_html($message));
            return;
        }

        printf(
            '<div class="notice notice-error is-dismissible"><p>%1$s <code>%2$s</code></p></div>',
            esc_html($message),
            esc_html($notice)
        );
    }

    /** @param list<array<string, mixed>> $courses */
    private function renderCourseList(array $courses): void
    {
        echo '<section class="amt-courses-card"><div class="amt-courses-card__heading"><div><h2>'
            . esc_html__('Kursy', 'am-toolkit') . '</h2><p>'
            . esc_html__('Szkice, opublikowane oferty i bezpiecznie zarchiwizowane kursy.', 'am-toolkit')
            . '</p></div></div>';

        if ($courses === []) {
            echo '<div class="amt-courses-empty"><p>' . esc_html__('Nie ma jeszcze żadnego kursu.', 'am-toolkit') . '</p></div></section>';
            return;
        }

        echo '<div class="amt-courses-list">';
        foreach ($courses as $course) {
            $id = (int) $course['id'];
            printf(
                '<a class="amt-course-row" href="%1$s"><span><strong>%2$s</strong><small>%3$s · %4$s</small></span><span aria-hidden="true">→</span></a>',
                esc_url($this->pageUrl($id)),
                esc_html((string) $course['title']),
                esc_html($this->statusLabel((string) $course['status'])),
                esc_html(sprintf(__('wersja %d', 'am-toolkit'), (int) ($course['current_version_number'] ?? 1)))
            );
        }
        echo '</div></section>';
    }

    /** @param array<string, mixed>|null $course */
    private function renderCourseForm(?array $course): void
    {
        $courseId = (int) ($course['id'] ?? 0);
        $selectedOperation = ($course['status'] ?? '') === PublicationStatus::PUBLISHED
            ? PublicationStatus::DRAFT
            : (string) ($course['status'] ?? PublicationStatus::DRAFT);
        ?>
        <section class="amt-courses-card" id="course-details">
            <div class="amt-courses-card__heading">
                <div>
                    <h2><?php echo esc_html($courseId > 0 ? __('Ustawienia kursu', 'am-toolkit') : __('Nowy kurs', 'am-toolkit')); ?></h2>
                    <p><?php esc_html_e('Publikacja tworzy niezmienny snapshot programu i nową wersję roboczą.', 'am-toolkit'); ?></p>
                </div>
                <?php if ($courseId > 0) : ?><span class="amt-status amt-status--<?php echo esc_attr((string) $course['status']); ?>"><?php echo esc_html($this->statusLabel((string) $course['status'])); ?></span><?php endif; ?>
            </div>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="amt-courses-form">
                <?php $this->formBase('save_course', $courseId); ?>
                <label><span><?php esc_html_e('Nazwa kursu', 'am-toolkit'); ?></span><input type="text" name="title" required maxlength="240" value="<?php echo esc_attr((string) ($course['title'] ?? '')); ?>"></label>
                <label><span><?php esc_html_e('Opis', 'am-toolkit'); ?></span><textarea name="description" rows="5"><?php echo esc_textarea((string) ($course['description'] ?? '')); ?></textarea></label>
                <div class="amt-courses-form__row">
                    <label><span><?php esc_html_e('ID grafiki w Media Library', 'am-toolkit'); ?></span><input type="number" min="0" name="image_attachment_id" value="<?php echo esc_attr((string) ($course['image_attachment_id'] ?? 0)); ?>"></label>
                    <label><span><?php esc_html_e('Operacja publikacji', 'am-toolkit'); ?></span><select name="status"><?php $this->statusOptions($selectedOperation); ?></select></label>
                </div>
                <div class="amt-courses-actions"><button class="button button-primary" type="submit"><?php esc_html_e('Zapisz kurs', 'am-toolkit'); ?></button></div>
            </form>
            <?php if ($courseId > 0 && ($course['status'] ?? '') !== PublicationStatus::ARCHIVED) : ?>
                <?php $this->archiveForm('archive_course', $courseId, 0, __('Archiwizuj kurs', 'am-toolkit')); ?>
            <?php endif; ?>
        </section>
        <?php
    }

    /** @param list<array<string, mixed>> $sections */
    private function renderSections(int $courseId, array $sections): void
    {
        echo '<section class="amt-courses-card"><div class="amt-courses-card__heading"><div><h2>'
            . esc_html__('Sekcje programu', 'am-toolkit') . '</h2><p>'
            . esc_html__('Pozycja określa kolejność. Archiwizacja nie usuwa historii.', 'am-toolkit') . '</p></div></div>';
        foreach ($sections as $section) {
            $this->sectionForm($courseId, $section);
        }
        $this->sectionForm($courseId, null, count($sections));
        echo '</section>';
    }

    /** @param array<string, mixed>|null $section */
    private function sectionForm(int $courseId, ?array $section, int $defaultPosition = 0): void
    {
        $id = (int) ($section['id'] ?? 0);
        ?>
        <details class="amt-editor" <?php echo $id === 0 ? 'open' : ''; ?>>
            <summary><span><?php echo esc_html($id > 0 ? (string) $section['title'] : __('Dodaj sekcję', 'am-toolkit')); ?></span><small><?php echo esc_html($id > 0 ? $this->statusLabel((string) $section['status']) : __('Nowa', 'am-toolkit')); ?></small></summary>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="amt-courses-form">
                <?php $this->formBase('save_section', $courseId); ?><input type="hidden" name="section_id" value="<?php echo esc_attr((string) $id); ?>">
                <label><span><?php esc_html_e('Nazwa sekcji', 'am-toolkit'); ?></span><input required name="title" value="<?php echo esc_attr((string) ($section['title'] ?? '')); ?>"></label>
                <label><span><?php esc_html_e('Opis', 'am-toolkit'); ?></span><textarea name="description" rows="3"><?php echo esc_textarea((string) ($section['description'] ?? '')); ?></textarea></label>
                <div class="amt-courses-form__row"><label><span><?php esc_html_e('Pozycja', 'am-toolkit'); ?></span><input type="number" min="0" name="position" value="<?php echo esc_attr((string) ($section['position'] ?? $defaultPosition)); ?>"></label><label><span><?php esc_html_e('Stan', 'am-toolkit'); ?></span><select name="status"><?php $this->statusOptions((string) ($section['status'] ?? PublicationStatus::DRAFT)); ?></select></label></div>
                <button class="button button-primary" type="submit"><?php esc_html_e('Zapisz sekcję', 'am-toolkit'); ?></button>
            </form>
            <?php if ($id > 0 && ($section['status'] ?? '') !== PublicationStatus::ARCHIVED) : ?><?php $this->archiveForm('archive_section', $courseId, $id, __('Archiwizuj sekcję', 'am-toolkit'), 'section_id'); ?><?php endif; ?>
        </details>
        <?php
    }

    /** @param list<array<string, mixed>> $sections @param list<array<string, mixed>> $lessons */
    private function renderLessons(int $courseId, array $sections, array $lessons): void
    {
        echo '<section class="amt-courses-card"><div class="amt-courses-card__heading"><div><h2>'
            . esc_html__('Lekcje', 'am-toolkit') . '</h2><p>'
            . esc_html__('Treść, wideo, wymagania ukończenia i miejsce w programie.', 'am-toolkit') . '</p></div></div>';
        foreach ($lessons as $lesson) {
            $this->lessonForm($courseId, $sections, $lesson);
        }
        $this->lessonForm($courseId, $sections, null);
        echo '</section>';
    }

    /** @param list<array<string, mixed>> $sections @param array<string, mixed>|null $lesson */
    private function lessonForm(int $courseId, array $sections, ?array $lesson): void
    {
        $id = (int) ($lesson['id'] ?? 0);
        $requirements = is_array($lesson['completion_requirements'] ?? null) ? $lesson['completion_requirements'] : [];
        ?>
        <details class="amt-editor" <?php echo $id === 0 ? 'open' : ''; ?>>
            <summary><span><?php echo esc_html($id > 0 ? (string) $lesson['title'] : __('Dodaj lekcję', 'am-toolkit')); ?></span><small><?php echo esc_html((string) ($lesson['section_title'] ?? __('Bez sekcji', 'am-toolkit'))); ?></small></summary>
            <form method="post" enctype="multipart/form-data" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="amt-courses-form">
                <?php $this->formBase('save_lesson', $courseId); ?><input type="hidden" name="lesson_id" value="<?php echo esc_attr((string) $id); ?>">
                <label><span><?php esc_html_e('Nazwa lekcji', 'am-toolkit'); ?></span><input required name="title" value="<?php echo esc_attr((string) ($lesson['title'] ?? '')); ?>"></label>
                <label><span><?php esc_html_e('Opis', 'am-toolkit'); ?></span><textarea name="description" rows="4"><?php echo esc_textarea((string) ($lesson['description'] ?? '')); ?></textarea></label>
                <div class="amt-courses-form__row"><label><span><?php esc_html_e('Sekcja', 'am-toolkit'); ?></span><select name="section_id"><option value="0"><?php esc_html_e('Bez sekcji', 'am-toolkit'); ?></option><?php foreach ($sections as $section) : ?><option value="<?php echo esc_attr((string) $section['id']); ?>" <?php selected((int) ($lesson['section_id'] ?? 0), (int) $section['id']); ?>><?php echo esc_html((string) $section['title']); ?></option><?php endforeach; ?></select></label><label><span><?php esc_html_e('Pozycja', 'am-toolkit'); ?></span><input type="number" min="0" name="position" value="<?php echo esc_attr((string) ($lesson['position'] ?? 0)); ?>"></label><label><span><?php esc_html_e('Stan', 'am-toolkit'); ?></span><select name="status"><?php $this->statusOptions((string) ($lesson['status'] ?? PublicationStatus::DRAFT)); ?></select></label></div>
                <input type="hidden" name="video_provider" value="<?php echo esc_attr((string) ($lesson['video_provider'] ?? '')); ?>">
                <input type="hidden" name="video_reference" value="<?php echo esc_attr((string) ($lesson['video_reference'] ?? '')); ?>">
                <div class="amt-courses-form__row">
                    <label><span><?php esc_html_e('Nagranie MP4', 'am-toolkit'); ?></span><input type="file" name="video_file" accept="video/mp4,.mp4"><small><?php echo esc_html(!empty($lesson['video_reference']) ? __('Nagranie jest zapisane. Nowy plik zastąpi przypisanie.', 'am-toolkit') : sprintf(__('Maksymalny rozmiar wysyłania: %s.', 'am-toolkit'), size_format(wp_max_upload_size()))); ?></small></label>
                    <label><span><?php esc_html_e('Czas nagrania w sekundach', 'am-toolkit'); ?></span><input type="number" min="0" name="duration_seconds" value="<?php echo esc_attr((string) ($lesson['duration_seconds'] ?? '')); ?>"><small><?php esc_html_e('Dla prywatnego MP4 czas zostanie odczytany automatycznie przy zapisie.', 'am-toolkit'); ?></small></label>
                </div>
                <div class="amt-courses-form__row"><label><span><?php esc_html_e('Wymagany procent filmu', 'am-toolkit'); ?></span><input type="number" min="0" max="100" name="video_percent" value="<?php echo esc_attr((string) ($requirements['video_percent'] ?? 0)); ?>"></label><label class="amt-check"><input type="checkbox" name="task_required" value="1" <?php checked(!empty($requirements['task_required'])); ?>><span><?php esc_html_e('Zadanie wymagane', 'am-toolkit'); ?></span></label><label class="amt-check"><input type="checkbox" name="is_required" value="1" <?php checked(!isset($lesson['is_required']) || (int) $lesson['is_required'] === 1); ?>><span><?php esc_html_e('Lekcja wymagana w programie', 'am-toolkit'); ?></span></label></div>
                <button class="button button-primary" type="submit"><?php esc_html_e('Zapisz lekcję', 'am-toolkit'); ?></button>
            </form>
            <?php if ($id > 0 && ($lesson['status'] ?? '') !== PublicationStatus::ARCHIVED) : ?><?php $this->archiveForm('archive_lesson', $courseId, $id, __('Archiwizuj lekcję', 'am-toolkit'), 'lesson_id'); ?><?php endif; ?>
        </details>
        <?php
    }

    /** @param list<array<string, mixed>> $lessons @param list<array<string, mixed>> $entries */
    private function renderQa(int $courseId, array $lessons, array $entries): void
    {
        ?>
        <section class="amt-courses-card" id="course-qa">
            <div class="amt-courses-card__heading">
                <div>
                    <h2><?php esc_html_e('Pytania i odpowiedzi (Q&A)', 'am-toolkit'); ?></h2>
                    <p><?php esc_html_e('Właścicielka publikuje odpowiedzi, a uczestniczki wyłącznie je czytają. Kolejność wyznacza pozycja.', 'am-toolkit'); ?></p>
                </div>
            </div>
            <?php foreach ($entries as $entry) : ?>
                <?php $this->qaForm($courseId, $lessons, $entry); ?>
            <?php endforeach; ?>
            <?php $this->qaForm($courseId, $lessons, null, count($entries)); ?>
        </section>
        <?php
    }

    /** @param list<array<string, mixed>> $lessons @param array<string, mixed>|null $entry */
    private function qaForm(int $courseId, array $lessons, ?array $entry, int $defaultPosition = 0): void
    {
        $id = (int) ($entry['id'] ?? 0);
        ?>
        <details class="amt-editor amt-qa-editor" <?php echo $id === 0 ? 'open' : ''; ?>>
            <summary>
                <span><?php echo esc_html($id > 0 ? (string) $entry['question'] : __('Dodaj pytanie i odpowiedź', 'am-toolkit')); ?></span>
                <small><?php echo esc_html($id > 0 ? $this->statusLabel((string) $entry['status']) : __('Nowe', 'am-toolkit')); ?></small>
            </summary>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="amt-courses-form">
                <?php $this->formBase('save_qa', $courseId); ?>
                <input type="hidden" name="qa_entry_id" value="<?php echo esc_attr((string) $id); ?>">
                <input type="hidden" name="public_id" value="<?php echo esc_attr((string) ($entry['public_id'] ?? '')); ?>">
                <label><span><?php esc_html_e('Pytanie', 'am-toolkit'); ?></span><textarea required maxlength="1000" name="question" rows="2"><?php echo esc_textarea((string) ($entry['question'] ?? '')); ?></textarea></label>
                <label><span><?php esc_html_e('Odpowiedź', 'am-toolkit'); ?></span><textarea required name="answer" rows="6"><?php echo esc_textarea((string) ($entry['answer'] ?? '')); ?></textarea></label>
                <div class="amt-courses-form__row">
                    <label><span><?php esc_html_e('Kontekst lekcji (opcjonalnie)', 'am-toolkit'); ?></span><select name="lesson_id"><option value="0"><?php esc_html_e('Cały kurs', 'am-toolkit'); ?></option><?php foreach ($lessons as $lesson) : ?><option value="<?php echo esc_attr((string) $lesson['id']); ?>" <?php selected((int) ($entry['lesson_id'] ?? 0), (int) $lesson['id']); ?>><?php echo esc_html((string) $lesson['title']); ?></option><?php endforeach; ?></select></label>
                    <label><span><?php esc_html_e('Pozycja', 'am-toolkit'); ?></span><input type="number" min="0" name="position" value="<?php echo esc_attr((string) ($entry['position'] ?? $defaultPosition)); ?>"></label>
                    <label><span><?php esc_html_e('Stan', 'am-toolkit'); ?></span><select name="status"><?php $this->statusOptions((string) ($entry['status'] ?? PublicationStatus::DRAFT)); ?></select></label>
                </div>
                <button class="button button-primary" type="submit"><?php esc_html_e('Zapisz Q&A', 'am-toolkit'); ?></button>
            </form>
            <?php if ($id > 0 && ($entry['status'] ?? '') !== PublicationStatus::ARCHIVED) : ?>
                <?php $this->archiveForm('archive_qa', $courseId, $id, __('Archiwizuj Q&A', 'am-toolkit'), 'qa_entry_id'); ?>
            <?php endif; ?>
        </details>
        <?php
    }

    /** @param list<array<string, mixed>> $lessons @param list<array<string, mixed>> $materials */
    private function renderMaterials(int $courseId, array $lessons, array $materials): void
    {
        echo '<section class="amt-courses-card"><div class="amt-courses-card__heading"><div><h2>'
            . esc_html__('Materiały', 'am-toolkit') . '</h2><p>'
            . esc_html__('Zapisuj identyfikator magazynu, nigdy publiczny URL jako kontrakt.', 'am-toolkit') . '</p></div></div>';
        foreach ($materials as $material) {
            $this->materialForm($courseId, $lessons, $material);
        }
        $this->materialForm($courseId, $lessons, null);
        echo '</section>';
    }

    /** @param list<array<string, mixed>> $lessons @param array<string, mixed>|null $material */
    private function materialForm(int $courseId, array $lessons, ?array $material): void
    {
        $id = (int) ($material['id'] ?? 0);
        ?>
        <details class="amt-editor" <?php echo $id === 0 ? 'open' : ''; ?>>
            <summary><span><?php echo esc_html($id > 0 ? (string) $material['name'] : __('Dodaj materiał', 'am-toolkit')); ?></span><small><?php echo esc_html((string) ($material['lesson_title'] ?? '')); ?></small></summary>
            <form method="post" enctype="multipart/form-data" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="amt-courses-form">
                <?php $this->formBase('save_material', $courseId); ?><input type="hidden" name="material_id" value="<?php echo esc_attr((string) $id); ?>">
                <label><span><?php esc_html_e('Lekcja', 'am-toolkit'); ?></span><select name="lesson_id" required><option value=""><?php esc_html_e('Wybierz lekcję', 'am-toolkit'); ?></option><?php foreach ($lessons as $lesson) : ?><option value="<?php echo esc_attr((string) $lesson['id']); ?>" <?php selected((int) ($material['lesson_id'] ?? 0), (int) $lesson['id']); ?>><?php echo esc_html((string) $lesson['title']); ?></option><?php endforeach; ?></select></label>
                <label><span><?php esc_html_e('Nazwa materiału', 'am-toolkit'); ?></span><input required name="name" value="<?php echo esc_attr((string) ($material['name'] ?? '')); ?>"></label>
                <label><span><?php esc_html_e('Opis', 'am-toolkit'); ?></span><textarea name="description" rows="3"><?php echo esc_textarea((string) ($material['description'] ?? '')); ?></textarea></label>
                <input type="hidden" name="storage_provider" value="<?php echo esc_attr((string) ($material['storage_provider'] ?? '')); ?>">
                <input type="hidden" name="storage_reference" value="<?php echo esc_attr((string) ($material['storage_reference'] ?? '')); ?>">
                <div class="amt-courses-form__row"><label><span><?php esc_html_e('Prywatny plik', 'am-toolkit'); ?></span><input type="file" name="material_file" <?php echo empty($material['storage_reference']) ? 'required' : ''; ?>><small><?php echo esc_html(!empty($material['storage_reference']) ? __('Plik jest zapisany. Nowy plik zastąpi przypisanie.', 'am-toolkit') : sprintf(__('Maksymalny rozmiar wysyłania: %s.', 'am-toolkit'), size_format(wp_max_upload_size()))); ?></small></label><label><span><?php esc_html_e('Pozycja', 'am-toolkit'); ?></span><input type="number" min="0" name="position" value="<?php echo esc_attr((string) ($material['position'] ?? 0)); ?>"></label><label><span><?php esc_html_e('Stan', 'am-toolkit'); ?></span><select name="status"><?php $this->statusOptions((string) ($material['status'] ?? PublicationStatus::DRAFT)); ?></select></label></div>
                <button class="button button-primary" type="submit"><?php esc_html_e('Zapisz materiał', 'am-toolkit'); ?></button>
            </form>
            <?php if ($id > 0 && ($material['status'] ?? '') !== PublicationStatus::ARCHIVED) : ?><?php $this->archiveForm('archive_material', $courseId, $id, __('Archiwizuj materiał', 'am-toolkit'), 'material_id', ['lesson_id' => (int) $material['lesson_id']]); ?><?php endif; ?>
        </details>
        <?php
    }

    /** @param list<array<string, mixed>> $meetings @param array<string, mixed> $settings */
    private function renderMeetings(int $courseId, array $meetings, array $settings): void
    {
        ?>
        <section class="amt-courses-card" id="course-meetings">
            <div class="amt-courses-card__heading">
                <div>
                    <h2><?php esc_html_e('Spotkania i linki prywatne', 'am-toolkit'); ?></h2>
                    <p><?php esc_html_e('Terminy są prezentowane w strefie Europe/Warsaw. Prywatne adresy zobaczą wyłącznie uczestniczki z aktywnym dostępem.', 'am-toolkit'); ?></p>
                </div>
            </div>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="amt-courses-form amt-private-links-form">
                <?php $this->formBase('save_course_links', $courseId); ?>
                <label>
                    <span><?php esc_html_e('Prywatny link do grupy Telegram', 'am-toolkit'); ?></span>
                    <input type="url" name="telegram_reference" inputmode="url" autocomplete="off" placeholder="https://t.me/..." value="<?php echo esc_attr((string) ($settings['telegram_reference'] ?? '')); ?>">
                    <small><?php esc_html_e('Pole opcjonalne. Adres nie trafia do logów ani eksportu diagnostycznego.', 'am-toolkit'); ?></small>
                </label>
                <button class="button button-primary" type="submit"><?php esc_html_e('Zapisz link kursu', 'am-toolkit'); ?></button>
            </form>

            <?php foreach ($meetings as $meeting) : ?>
                <?php $this->meetingForm($courseId, $meeting); ?>
            <?php endforeach; ?>
            <?php $this->meetingForm($courseId, null); ?>
        </section>
        <?php
    }

    /** @param array<string, mixed>|null $meeting */
    private function meetingForm(int $courseId, ?array $meeting): void
    {
        $id = (int) ($meeting['id'] ?? 0);
        $timezone = (string) ($meeting['display_timezone'] ?? 'Europe/Warsaw');
        ?>
        <details class="amt-editor amt-meeting-editor" <?php echo $id === 0 ? 'open' : ''; ?>>
            <summary>
                <span><?php echo esc_html($id > 0 ? (string) $meeting['title'] : __('Dodaj spotkanie', 'am-toolkit')); ?></span>
                <small><?php echo esc_html($id > 0 ? $this->meetingStatusLabel((string) $meeting['status']) : __('Nowe', 'am-toolkit')); ?></small>
            </summary>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="amt-courses-form">
                <?php $this->formBase('save_meeting', $courseId); ?>
                <input type="hidden" name="meeting_id" value="<?php echo esc_attr((string) $id); ?>">
                <label><span><?php esc_html_e('Nazwa spotkania', 'am-toolkit'); ?></span><input required maxlength="240" name="title" value="<?php echo esc_attr((string) ($meeting['title'] ?? '')); ?>"></label>
                <label><span><?php esc_html_e('Opis', 'am-toolkit'); ?></span><textarea name="description" rows="3"><?php echo esc_textarea((string) ($meeting['description'] ?? '')); ?></textarea></label>
                <div class="amt-courses-form__row">
                    <label><span><?php esc_html_e('Początek', 'am-toolkit'); ?></span><input required type="datetime-local" name="starts_at" value="<?php echo esc_attr($this->meetingLocalValue((string) ($meeting['starts_at_utc'] ?? ''), $timezone)); ?>"></label>
                    <label><span><?php esc_html_e('Koniec', 'am-toolkit'); ?></span><input required type="datetime-local" name="ends_at" value="<?php echo esc_attr($this->meetingLocalValue((string) ($meeting['ends_at_utc'] ?? ''), $timezone)); ?>"></label>
                    <label><span><?php esc_html_e('Strefa czasowa', 'am-toolkit'); ?></span><input required name="display_timezone" value="<?php echo esc_attr($timezone); ?>" readonly></label>
                </div>
                <div class="amt-courses-form__row">
                    <label><span><?php esc_html_e('Platforma', 'am-toolkit'); ?></span><input name="platform" placeholder="Zoom" value="<?php echo esc_attr((string) ($meeting['platform'] ?? '')); ?>"></label>
                    <label><span><?php esc_html_e('Miejsce / dodatkowa informacja', 'am-toolkit'); ?></span><input name="location" value="<?php echo esc_attr((string) ($meeting['location'] ?? '')); ?>"></label>
                    <label><span><?php esc_html_e('Status', 'am-toolkit'); ?></span><select name="meeting_status"><?php $this->meetingStatusOptions((string) ($meeting['status'] ?? MeetingStatus::SCHEDULED)); ?></select></label>
                </div>
                <div class="amt-courses-form__row">
                    <label><span><?php esc_html_e('Prywatny link do spotkania', 'am-toolkit'); ?></span><input type="url" inputmode="url" autocomplete="off" name="join_reference" placeholder="https://..." value="<?php echo esc_attr((string) ($meeting['join_reference'] ?? '')); ?>"></label>
                    <label><span><?php esc_html_e('Prywatny link do nagrania', 'am-toolkit'); ?></span><input type="url" inputmode="url" autocomplete="off" name="recording_reference" placeholder="https://..." value="<?php echo esc_attr((string) ($meeting['recording_reference'] ?? '')); ?>"></label>
                </div>
                <button class="button button-primary" type="submit"><?php esc_html_e('Zapisz spotkanie', 'am-toolkit'); ?></button>
            </form>
        </details>
        <?php
    }

    private function meetingStatusOptions(string $selected): void
    {
        foreach ([
            MeetingStatus::SCHEDULED => __('Zaplanowane', 'am-toolkit'),
            MeetingStatus::RESCHEDULED => __('Przesunięte', 'am-toolkit'),
            MeetingStatus::CANCELLED => __('Odwołane', 'am-toolkit'),
            MeetingStatus::COMPLETED => __('Zakończone', 'am-toolkit'),
        ] as $value => $label) {
            printf('<option value="%1$s" %2$s>%3$s</option>', esc_attr($value), selected($selected, $value, false), esc_html($label));
        }
    }

    private function meetingStatusLabel(string $status): string
    {
        return [
            MeetingStatus::SCHEDULED => __('Zaplanowane', 'am-toolkit'),
            MeetingStatus::RESCHEDULED => __('Przesunięte', 'am-toolkit'),
            MeetingStatus::CANCELLED => __('Odwołane', 'am-toolkit'),
            MeetingStatus::COMPLETED => __('Zakończone', 'am-toolkit'),
        ][$status] ?? $status;
    }

    private function meetingLocalValue(string $utcValue, string $timezone): string
    {
        if ($utcValue === '') {
            return '';
        }

        try {
            return (new \DateTimeImmutable($utcValue, new \DateTimeZone('UTC')))
                ->setTimezone(new \DateTimeZone($timezone))
                ->format('Y-m-d\TH:i');
        } catch (\Throwable) {
            return '';
        }
    }

    /** @param list<int> $selected */
    private function renderMappings(int $courseId, array $selected): void
    {
        $products = function_exists('wc_get_products')
            ? wc_get_products(['status' => ['publish', 'draft'], 'limit' => -1, 'orderby' => 'name', 'order' => 'ASC'])
            : [];
        ?>
        <section class="amt-courses-card">
            <h2><?php esc_html_e('Produkty WooCommerce', 'am-toolkit'); ?></h2>
            <p><?php esc_html_e('Mapowanie wskazuje ofertę, ale samo nie jest grantem dostępu.', 'am-toolkit'); ?></p>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="amt-courses-form">
                <?php $this->formBase('save_mappings', $courseId); ?>
                <div class="amt-product-checklist">
                    <?php foreach ($products as $product) : $id = (int) $product->get_id(); ?>
                        <label class="amt-check"><input type="checkbox" name="product_ids[]" value="<?php echo esc_attr((string) $id); ?>" <?php checked(in_array($id, $selected, true)); ?>><span><?php echo esc_html($product->get_name()); ?> <small>#<?php echo esc_html((string) $id); ?></small></span></label>
                    <?php endforeach; ?>
                    <?php if ($products === []) : ?><p class="description"><?php esc_html_e('Brak produktów do przypisania.', 'am-toolkit'); ?></p><?php endif; ?>
                </div>
                <button class="button button-primary" type="submit"><?php esc_html_e('Zapisz mapowania', 'am-toolkit'); ?></button>
            </form>
        </section>
        <?php
    }

    /** @param list<array<string, mixed>> $participants @param list<array<string, mixed>> $activity */
    private function renderAccess(int $courseId, array $participants, array $activity): void
    {
        $users = get_users(['fields' => ['ID', 'display_name', 'user_login'], 'orderby' => 'display_name']);
        ?>
        <section class="amt-courses-card">
            <h2><?php esc_html_e('Uczestnicy i dostęp', 'am-toolkit'); ?></h2>
            <?php if (Authorization::canManageAccess()) : ?>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="amt-courses-form">
                    <?php $this->formBase('grant_manual', $courseId); ?>
                    <label><span><?php esc_html_e('Nadaj dostęp użytkownikowi', 'am-toolkit'); ?></span><select name="user_id" required><option value=""><?php esc_html_e('Wybierz konto', 'am-toolkit'); ?></option><?php foreach ($users as $user) : ?><option value="<?php echo esc_attr((string) $user->ID); ?>"><?php echo esc_html($user->display_name . ' (' . $user->user_login . ')'); ?></option><?php endforeach; ?></select></label>
                    <button class="button button-primary" type="submit"><?php esc_html_e('Nadaj ręcznie', 'am-toolkit'); ?></button>
                </form>
            <?php endif; ?>
            <div class="amt-table-scroll"><table class="widefat striped"><thead><tr><th><?php esc_html_e('Użytkownik', 'am-toolkit'); ?></th><th><?php esc_html_e('Źródło', 'am-toolkit'); ?></th><th><?php esc_html_e('Stan', 'am-toolkit'); ?></th><th></th></tr></thead><tbody>
            <?php if ($participants === []) : ?><tr><td colspan="4"><?php esc_html_e('Brak historii dostępu.', 'am-toolkit'); ?></td></tr><?php endif; ?>
            <?php foreach ($participants as $grant) : ?><tr><td><?php echo esc_html((string) $grant['display_name']); ?><small class="amt-block">@<?php echo esc_html((string) $grant['user_login']); ?></small></td><td><code><?php echo esc_html((string) $grant['source_type']); ?></code></td><td><?php echo esc_html((string) $grant['status']); ?></td><td><?php if (($grant['source_type'] ?? '') === 'manual' && ($grant['status'] ?? '') === 'active' && Authorization::canManageAccess()) : ?><form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><?php $this->formBase('revoke_manual', $courseId); ?><input type="hidden" name="assignment_id" value="<?php echo esc_attr((string) $grant['source_id']); ?>"><button class="button-link-delete" type="submit"><?php esc_html_e('Odbierz', 'am-toolkit'); ?></button></form><?php endif; ?></td></tr><?php endforeach; ?>
            </tbody></table></div>
        </section>
        <section class="amt-courses-card">
            <h2><?php esc_html_e('Historia zmian', 'am-toolkit'); ?></h2>
            <div class="amt-activity-list">
                <?php if ($activity === []) : ?><p><?php esc_html_e('Brak zdarzeń dla tego kursu.', 'am-toolkit'); ?></p><?php endif; ?>
                <?php foreach ($activity as $event) : ?><div><strong><?php echo esc_html((string) $event['event_type']); ?></strong><small><?php echo esc_html((string) $event['occurred_at']); ?> · <?php echo esc_html((string) $event['request_id']); ?></small></div><?php endforeach; ?>
            </div>
        </section>
        <?php
    }

    private function formBase(string $intent, int $courseId): void
    {
        wp_nonce_field(self::NONCE_ACTION, self::NONCE_NAME);
        echo '<input type="hidden" name="action" value="am_toolkit_courses_admin">';
        echo '<input type="hidden" name="intent" value="' . esc_attr($intent) . '">';
        echo '<input type="hidden" name="course_id" value="' . esc_attr((string) $courseId) . '">';
    }

    /** @param array<string, int> $extra */
    private function archiveForm(string $intent, int $courseId, int $resourceId, string $label, string $field = '', array $extra = []): void
    {
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="amt-archive-form">';
        $this->formBase($intent, $courseId);
        if ($field !== '') {
            echo '<input type="hidden" name="' . esc_attr($field) . '" value="' . esc_attr((string) $resourceId) . '">';
        }
        foreach ($extra as $name => $value) {
            echo '<input type="hidden" name="' . esc_attr($name) . '" value="' . esc_attr((string) $value) . '">';
        }
        echo '<button class="button-link-delete" type="submit">' . esc_html($label) . '</button></form>';
    }

    private function statusOptions(string $selected): void
    {
        $labels = [
            PublicationStatus::DRAFT => __('Zapisz bez publikacji', 'am-toolkit'),
            PublicationStatus::PUBLISHED => __('Opublikuj', 'am-toolkit'),
            PublicationStatus::ARCHIVED => __('Archiwum', 'am-toolkit'),
        ];
        foreach ($labels as $value => $label) {
            printf('<option value="%1$s" %2$s>%3$s</option>', esc_attr($value), selected($selected, $value, false), esc_html($label));
        }
    }

    private function statusLabel(string $status): string
    {
        return [
            PublicationStatus::DRAFT => __('Szkic', 'am-toolkit'),
            PublicationStatus::PUBLISHED => __('Opublikowany', 'am-toolkit'),
            PublicationStatus::ARCHIVED => __('Archiwum', 'am-toolkit'),
        ][$status] ?? $status;
    }

    private function postText(string $key): string
    {
        return sanitize_text_field($this->postValue($key));
    }

    private function postTextarea(string $key): string
    {
        return sanitize_textarea_field($this->postValue($key));
    }

    private function postUrl(string $key): string
    {
        return trim($this->postValue($key));
    }

    private function postKey(string $key): string
    {
        return sanitize_key($this->postText($key));
    }

    private function postInt(string $key): int
    {
        return absint($this->postValue($key));
    }

    private function postOptionalInt(string $key): ?int
    {
        $value = $this->postValue($key);

        if ($value === '') {
            return null;
        }

        return absint($value);
    }

    private function postBool(string $key): bool
    {
        return $this->postValue($key) === '1';
    }

    private function postStatus(): string
    {
        $status = $this->postKey('status');

        return in_array($status, PublicationStatus::all(), true) ? $status : '';
    }

    private function postValue(string $key): string
    {
        if (!isset($_POST[$key]) || !is_scalar($_POST[$key])) {
            return '';
        }

        return (string) wp_unslash($_POST[$key]);
    }

    /** @return array<string, mixed>|null */
    private function uploadedFile(string $key): ?array
    {
        if (!isset($_FILES[$key]) || !is_array($_FILES[$key])) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
            return null;
        }

        $upload = $_FILES[$key]; // phpcs:ignore WordPress.Security.NonceVerification.Missing

        return isset($upload['error']) && (int) $upload['error'] === UPLOAD_ERR_NO_FILE
            ? null
            : $upload;
    }

    private function queryValue(string $key): string
    {
        // Reading a navigation parameter does not mutate state and needs no nonce.
        if (!isset($_GET[$key]) || !is_scalar($_GET[$key])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            return '';
        }

        return (string) wp_unslash($_GET[$key]); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    }

    private function redirect(int $courseId, string $notice): void
    {
        $url = add_query_arg(
            array_filter(['page' => self::PAGE_SLUG, 'course_id' => $courseId, 'amt_notice' => $notice]),
            admin_url('admin.php')
        );
        wp_safe_redirect($url);
        exit;
    }

    private function pageUrl(int $courseId = 0): string
    {
        return add_query_arg(
            array_filter(['page' => self::PAGE_SLUG, 'course_id' => $courseId]),
            admin_url('admin.php')
        );
    }

    /** @return array<mixed> */
    private function valueOrEmpty(mixed $value): array
    {
        return is_array($value) ? $value : [];
    }
}
