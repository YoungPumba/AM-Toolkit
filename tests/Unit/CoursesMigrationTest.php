<?php

declare(strict_types=1);

namespace AMToolkit\Tests\Unit;

use AMToolkit\Modules\Courses\CoursesSchema;
use AMToolkit\Modules\Courses\Migrations\CreateCoursesCatalogTables;
use AMToolkit\Modules\Courses\Migrations\CreateCoursesProgressTables;
use AMToolkit\Modules\Courses\Migrations\CreateCourseProductMappingsTable;
use AMToolkit\Modules\Courses\Migrations\CreateCourseMeetingsTables;
use AMToolkit\Modules\Courses\Migrations\CreateCourseQaTable;
use AMToolkit\Modules\Courses\Migrations\CreateLessonTaskTables;
use AMToolkit\Modules\Courses\Migrations\UpgradeCoursesProgressSources;
use PHPUnit\Framework\TestCase;

final class CoursesMigrationTest extends TestCase
{
    private CoursesMigrationWpdb $database;

    protected function setUp(): void
    {
        $this->database = new CoursesMigrationWpdb();
        $GLOBALS['wpdb'] = $this->database;
        $GLOBALS['amt_test_dbdelta_handler'] = function (string|array $queries): array {
            foreach ((array) $queries as $query) {
                $this->database->applySchema($query);
            }

            return [];
        };
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['amt_test_dbdelta_handler'], $GLOBALS['wpdb']);
    }

    public function testMigrationsCreateAndVerifyAnEmptyDatabase(): void
    {
        self::assertTrue((new CreateCoursesCatalogTables())->up());
        self::assertTrue((new CreateCoursesProgressTables())->up());
        self::assertTrue((new CreateCourseProductMappingsTable())->up());
        self::assertTrue((new UpgradeCoursesProgressSources())->up());
        self::assertTrue((new CreateCourseMeetingsTables())->up());
        self::assertTrue((new CreateCourseQaTable())->up());
        self::assertTrue((new CreateLessonTaskTables())->up());
        self::assertCount(16, $this->database->tables);

        self::assertArrayHasKey('course_version', $this->database->indexes[CoursesSchema::programVersionsTable()]);
        self::assertArrayHasKey('program_lesson', $this->database->indexes[CoursesSchema::programLessonsTable()]);
        self::assertArrayHasKey('user_course_lesson', $this->database->indexes[CoursesSchema::progressTable()]);
        self::assertArrayHasKey('user_course_program', $this->database->indexes[CoursesSchema::completionsTable()]);
        self::assertArrayHasKey('product_course', $this->database->indexes[CoursesSchema::productMappingsTable()]);
        self::assertArrayHasKey('user_lesson_request', $this->database->indexes[CoursesSchema::videoCheckpointsTable()]);
        self::assertArrayHasKey('user_lesson_requirement', $this->database->indexes[CoursesSchema::requirementCompletionsTable()]);
        self::assertArrayHasKey('course_schedule', $this->database->indexes[CoursesSchema::meetingsTable()]);
        self::assertArrayHasKey('meeting_revision', $this->database->indexes[CoursesSchema::meetingRevisionsTable()]);
        self::assertArrayHasKey('course_qa_order', $this->database->indexes[CoursesSchema::qaEntriesTable()]);
        self::assertArrayHasKey('lesson_qa_context', $this->database->indexes[CoursesSchema::qaEntriesTable()]);
        self::assertArrayHasKey('lesson_task_order', $this->database->indexes[CoursesSchema::lessonTasksTable()]);
        self::assertArrayHasKey('user_task', $this->database->indexes[CoursesSchema::lessonTaskProgressTable()]);
        self::assertArrayHasKey('lesson_task_progress', $this->database->indexes[CoursesSchema::lessonTaskProgressTable()]);
    }

    public function testMigrationsCanRunAgainWithoutChangingTheSchema(): void
    {
        $catalog = new CreateCoursesCatalogTables();
        $progress = new CreateCoursesProgressTables();
        $mappings = new CreateCourseProductMappingsTable();
        $sources = new UpgradeCoursesProgressSources();
        $meetings = new CreateCourseMeetingsTables();
        $qa = new CreateCourseQaTable();
        $tasks = new CreateLessonTaskTables();

        self::assertTrue($catalog->up());
        self::assertTrue($progress->up());
        self::assertTrue($mappings->up());
        self::assertTrue($sources->up());
        self::assertTrue($meetings->up());
        self::assertTrue($qa->up());
        self::assertTrue($tasks->up());
        $firstSchema = [$this->database->tables, $this->database->indexes];

        self::assertTrue($catalog->up());
        self::assertTrue($progress->up());
        self::assertTrue($mappings->up());
        self::assertTrue($sources->up());
        self::assertTrue($meetings->up());
        self::assertTrue($qa->up());
        self::assertTrue($tasks->up());
        self::assertSame($firstSchema, [$this->database->tables, $this->database->indexes]);
    }

    public function testPublishedMigrationsContainNoDestructiveStatements(): void
    {
        $definitions = array_merge(
            CoursesSchema::catalogDefinitions(''),
            CoursesSchema::progressDefinitions('')
        );
        $definitions = array_merge($definitions, CoursesSchema::progressSourceDefinitions(''));
        $definitions = array_merge($definitions, CoursesSchema::meetingDefinitions(''));
        $definitions = array_merge($definitions, CoursesSchema::qaDefinitions(''));
        $definitions = array_merge($definitions, CoursesSchema::lessonTaskDefinitions(''));
        $definitions[] = CoursesSchema::productMappingDefinition('');
        $sql = strtoupper(implode("\n", $definitions));

        self::assertStringNotContainsString('DROP TABLE', $sql);
        self::assertStringNotContainsString('TRUNCATE', $sql);
        self::assertStringNotContainsString('DELETE FROM', $sql);
    }
}

final class CoursesMigrationWpdb
{
    public string $prefix = 'wp_';

    /** @var array<string, true> */
    public array $tables = [];

    /** @var array<string, array<string, true>> */
    public array $indexes = [];

    public function get_charset_collate(): string
    {
        return 'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci';
    }

    public function esc_like(string $value): string
    {
        return $value;
    }

    public function prepare(string $query, mixed ...$args): string
    {
        foreach ($args as $arg) {
            $query = preg_replace('/%s/', "'" . (string) $arg . "'", $query, 1) ?? $query;
        }

        return $query;
    }

    public function get_var(string $query, int $column = 0): ?string
    {
        if (preg_match("/SHOW TABLES LIKE '([^']+)'/", $query, $match)) {
            return isset($this->tables[$match[1]]) ? $match[1] : null;
        }

        if (preg_match("/SHOW INDEX FROM ([^ ]+) WHERE Key_name = '([^']+)'/", $query, $match)) {
            return isset($this->indexes[$match[1]][$match[2]]) ? $match[2] : null;
        }

        return null;
    }

    public function applySchema(string $sql): void
    {
        if (! preg_match('/CREATE TABLE ([^ (]+)/i', $sql, $tableMatch)) {
            return;
        }

        $table = $tableMatch[1];
        $this->tables[$table] = true;
        $this->indexes[$table] ??= [];

        preg_match_all('/(?:UNIQUE\s+)?KEY\s+([a-z0-9_]+)\s*\(/i', $sql, $indexMatches);

        foreach ($indexMatches[1] as $index) {
            $this->indexes[$table][$index] = true;
        }
    }
}
