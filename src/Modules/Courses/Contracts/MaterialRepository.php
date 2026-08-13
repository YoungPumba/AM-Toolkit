<?php

namespace AMToolkit\Modules\Courses\Contracts;

use AMToolkit\Modules\Courses\Domain\LessonMaterial;

defined('ABSPATH') || exit;

interface MaterialRepository
{
    /** @return list<LessonMaterial> */
    public function findForLesson(int $lessonId): array;

    public function save(LessonMaterial $material): int;
}
