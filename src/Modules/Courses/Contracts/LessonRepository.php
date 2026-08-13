<?php

namespace AMToolkit\Modules\Courses\Contracts;

use AMToolkit\Modules\Courses\Domain\Lesson;

defined('ABSPATH') || exit;

interface LessonRepository
{
    public function find(int $lessonId): ?Lesson;

    /** @return list<Lesson> */
    public function findForProgram(int $programVersionId): array;

    public function save(Lesson $lesson): int;
}
