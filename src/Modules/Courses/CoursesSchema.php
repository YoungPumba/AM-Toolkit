<?php

namespace AMToolkit\Modules\Courses;

defined('ABSPATH') || exit;

final class CoursesSchema
{
    public const VERSION = 6;

    public static function coursesTable(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'amt_courses';
    }

    public static function programVersionsTable(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'amt_course_program_versions';
    }

    public static function sectionsTable(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'amt_course_sections';
    }

    public static function lessonsTable(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'amt_lessons';
    }

    public static function programLessonsTable(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'amt_program_lessons';
    }

    public static function materialsTable(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'amt_lesson_materials';
    }

    public static function progressTable(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'amt_lesson_progress';
    }

    public static function completionsTable(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'amt_course_completions';
    }

    public static function videoCheckpointsTable(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'amt_lesson_video_checkpoints';
    }

    public static function requirementCompletionsTable(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'amt_lesson_requirement_completions';
    }

    public static function productMappingsTable(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'amt_course_product_mappings';
    }

    public static function meetingsTable(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'amt_course_meetings';
    }

    public static function meetingRevisionsTable(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'amt_course_meeting_revisions';
    }

    public static function qaEntriesTable(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'amt_course_qa_entries';
    }

    public static function productMappingDefinition(string $charsetCollate): string
    {
        $mappings = self::productMappingsTable();

        return "CREATE TABLE {$mappings} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            product_id bigint(20) unsigned NOT NULL,
            course_id bigint(20) unsigned NOT NULL,
            status varchar(24) NOT NULL DEFAULT 'active',
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY product_course (product_id, course_id),
            KEY active_product (product_id, status),
            KEY active_course (course_id, status)
        ) {$charsetCollate};";
    }

    /** @return array<string, string> */
    public static function catalogDefinitions(string $charsetCollate): array
    {
        $courses = self::coursesTable();
        $programVersions = self::programVersionsTable();
        $sections = self::sectionsTable();
        $lessons = self::lessonsTable();
        $programLessons = self::programLessonsTable();
        $materials = self::materialsTable();

        return [
            $courses => "CREATE TABLE {$courses} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                public_id char(36) NOT NULL,
                title text NOT NULL,
                description longtext NULL,
                image_attachment_id bigint(20) unsigned NOT NULL DEFAULT 0,
                telegram_reference varchar(500) NULL DEFAULT NULL,
                status varchar(24) NOT NULL DEFAULT 'draft',
                current_program_version_id bigint(20) unsigned NULL DEFAULT NULL,
                created_at datetime NOT NULL,
                updated_at datetime NOT NULL,
                archived_at datetime NULL DEFAULT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY public_id (public_id),
                KEY course_status (status, archived_at),
                KEY current_program (current_program_version_id)
            ) {$charsetCollate};",
            $programVersions => "CREATE TABLE {$programVersions} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                public_id char(36) NOT NULL,
                course_id bigint(20) unsigned NOT NULL,
                version_number int(10) unsigned NOT NULL,
                status varchar(24) NOT NULL DEFAULT 'draft',
                content_hash char(64) NOT NULL,
                published_at datetime NULL DEFAULT NULL,
                created_at datetime NOT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY public_id (public_id),
                UNIQUE KEY course_version (course_id, version_number),
                KEY published_program (course_id, status, published_at)
            ) {$charsetCollate};",
            $sections => "CREATE TABLE {$sections} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                public_id char(36) NOT NULL,
                program_version_id bigint(20) unsigned NOT NULL,
                title text NOT NULL,
                description longtext NULL,
                position int(10) unsigned NOT NULL DEFAULT 0,
                status varchar(24) NOT NULL DEFAULT 'draft',
                created_at datetime NOT NULL,
                updated_at datetime NOT NULL,
                archived_at datetime NULL DEFAULT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY public_id (public_id),
                UNIQUE KEY program_position (program_version_id, position),
                KEY section_program (program_version_id, status)
            ) {$charsetCollate};",
            $lessons => "CREATE TABLE {$lessons} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                public_id char(36) NOT NULL,
                course_id bigint(20) unsigned NOT NULL,
                title text NOT NULL,
                description longtext NULL,
                status varchar(24) NOT NULL DEFAULT 'draft',
                video_provider varchar(64) NULL DEFAULT NULL,
                video_reference varchar(191) NULL DEFAULT NULL,
                duration_seconds int(10) unsigned NULL DEFAULT NULL,
                completion_requirements longtext NULL,
                content_version int(10) unsigned NOT NULL DEFAULT 1,
                created_at datetime NOT NULL,
                updated_at datetime NOT NULL,
                archived_at datetime NULL DEFAULT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY public_id (public_id),
                KEY course_lessons (course_id, status, archived_at)
            ) {$charsetCollate};",
            $programLessons => "CREATE TABLE {$programLessons} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                program_version_id bigint(20) unsigned NOT NULL,
                lesson_id bigint(20) unsigned NOT NULL,
                section_id bigint(20) unsigned NULL DEFAULT NULL,
                position int(10) unsigned NOT NULL DEFAULT 0,
                is_required tinyint(1) unsigned NOT NULL DEFAULT 1,
                PRIMARY KEY  (id),
                UNIQUE KEY program_lesson (program_version_id, lesson_id),
                KEY program_order (program_version_id, section_id, position),
                KEY lesson_programs (lesson_id, program_version_id)
            ) {$charsetCollate};",
            $materials => "CREATE TABLE {$materials} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                public_id char(36) NOT NULL,
                lesson_id bigint(20) unsigned NOT NULL,
                name text NOT NULL,
                description longtext NULL,
                storage_provider varchar(64) NOT NULL,
                storage_reference varchar(191) NOT NULL,
                position int(10) unsigned NOT NULL DEFAULT 0,
                status varchar(24) NOT NULL DEFAULT 'draft',
                created_at datetime NOT NULL,
                updated_at datetime NOT NULL,
                archived_at datetime NULL DEFAULT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY public_id (public_id),
                KEY lesson_materials (lesson_id, status, position)
            ) {$charsetCollate};",
        ];
    }

    /** @return array<string, string> */
    public static function meetingDefinitions(string $charsetCollate): array
    {
        $meetings = self::meetingsTable();
        $revisions = self::meetingRevisionsTable();

        return [
            $meetings => "CREATE TABLE {$meetings} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                public_id char(36) NOT NULL,
                course_id bigint(20) unsigned NOT NULL,
                title text NOT NULL,
                description longtext NULL,
                starts_at_utc datetime NOT NULL,
                ends_at_utc datetime NOT NULL,
                display_timezone varchar(64) NOT NULL DEFAULT 'Europe/Warsaw',
                platform varchar(120) NULL DEFAULT NULL,
                location text NULL,
                join_reference varchar(500) NULL DEFAULT NULL,
                recording_reference varchar(500) NULL DEFAULT NULL,
                status varchar(24) NOT NULL DEFAULT 'scheduled',
                created_at datetime NOT NULL,
                updated_at datetime NOT NULL,
                archived_at datetime NULL DEFAULT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY public_id (public_id),
                KEY course_schedule (course_id, starts_at_utc, status),
                KEY meeting_status (status, starts_at_utc)
            ) {$charsetCollate};",
            $revisions => "CREATE TABLE {$revisions} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                meeting_id bigint(20) unsigned NOT NULL,
                course_id bigint(20) unsigned NOT NULL,
                revision_number int(10) unsigned NOT NULL,
                snapshot longtext NOT NULL,
                actor_id bigint(20) unsigned NOT NULL DEFAULT 0,
                request_id varchar(32) NOT NULL,
                created_at datetime NOT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY meeting_revision (meeting_id, revision_number),
                KEY course_revisions (course_id, created_at)
            ) {$charsetCollate};",
        ];
    }

    /** @return array<string, string> */
    public static function qaDefinitions(string $charsetCollate): array
    {
        $entries = self::qaEntriesTable();

        return [
            $entries => "CREATE TABLE {$entries} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                public_id char(36) NOT NULL,
                course_id bigint(20) unsigned NOT NULL,
                lesson_id bigint(20) unsigned NULL DEFAULT NULL,
                question text NOT NULL,
                answer longtext NOT NULL,
                position int(10) unsigned NOT NULL DEFAULT 0,
                status varchar(24) NOT NULL DEFAULT 'draft',
                created_at datetime NOT NULL,
                updated_at datetime NOT NULL,
                archived_at datetime NULL DEFAULT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY public_id (public_id),
                KEY course_qa_order (course_id, status, position, id),
                KEY lesson_qa_context (lesson_id, status)
            ) {$charsetCollate};",
        ];
    }

    /** @return array<string, string> */
    public static function progressDefinitions(string $charsetCollate): array
    {
        $progress = self::progressTable();
        $completions = self::completionsTable();

        return [
            $progress => "CREATE TABLE {$progress} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                user_id bigint(20) unsigned NOT NULL,
                course_id bigint(20) unsigned NOT NULL,
                lesson_id bigint(20) unsigned NOT NULL,
                status varchar(24) NOT NULL DEFAULT 'started',
                completion_source varchar(64) NULL DEFAULT NULL,
                request_id varchar(32) NULL DEFAULT NULL,
                content_version int(10) unsigned NOT NULL DEFAULT 1,
                completed_at datetime NULL DEFAULT NULL,
                created_at datetime NOT NULL,
                updated_at datetime NOT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY user_course_lesson (user_id, course_id, lesson_id),
                KEY course_progress (course_id, user_id, status),
                KEY lesson_progress (lesson_id, status)
            ) {$charsetCollate};",
            $completions => "CREATE TABLE {$completions} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                user_id bigint(20) unsigned NOT NULL,
                course_id bigint(20) unsigned NOT NULL,
                program_version_id bigint(20) unsigned NOT NULL,
                required_lesson_ids longtext NOT NULL,
                requirements_hash char(64) NOT NULL,
                completion_source varchar(64) NOT NULL,
                request_id varchar(32) NULL DEFAULT NULL,
                completed_at datetime NOT NULL,
                created_at datetime NOT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY user_course_program (user_id, course_id, program_version_id),
                KEY course_completions (course_id, completed_at),
                KEY user_completions (user_id, completed_at)
            ) {$charsetCollate};",
        ];
    }

    /** @return array<string, string> */
    public static function progressSourceDefinitions(string $charsetCollate): array
    {
        $checkpoints = self::videoCheckpointsTable();
        $requirements = self::requirementCompletionsTable();

        return [
            $checkpoints => "CREATE TABLE {$checkpoints} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                user_id bigint(20) unsigned NOT NULL,
                course_id bigint(20) unsigned NOT NULL,
                lesson_id bigint(20) unsigned NOT NULL,
                content_version int(10) unsigned NOT NULL,
                request_id varchar(32) NOT NULL,
                intervals longtext NOT NULL,
                duration_seconds int(10) unsigned NOT NULL,
                covered_seconds decimal(12,3) unsigned NOT NULL DEFAULT 0,
                occurred_at datetime NOT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY user_lesson_request (user_id, lesson_id, request_id),
                KEY lesson_checkpoint_source (user_id, course_id, lesson_id, content_version)
            ) {$charsetCollate};",
            $requirements => "CREATE TABLE {$requirements} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                user_id bigint(20) unsigned NOT NULL,
                course_id bigint(20) unsigned NOT NULL,
                lesson_id bigint(20) unsigned NOT NULL,
                content_version int(10) unsigned NOT NULL,
                requirement_key varchar(64) NOT NULL,
                completion_source varchar(64) NOT NULL,
                request_id varchar(32) NOT NULL,
                completed_at datetime NOT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY user_lesson_requirement (user_id, lesson_id, content_version, requirement_key),
                KEY course_requirement_source (user_id, course_id, content_version)
            ) {$charsetCollate};",
        ];
    }
}
