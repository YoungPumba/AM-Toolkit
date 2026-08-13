<?php

namespace AMToolkit\Modules\Courses\Contracts;

use AMToolkit\Modules\Courses\Domain\CourseSection;

defined('ABSPATH') || exit;

interface SectionRepository
{
    /** @return list<CourseSection> */
    public function findForProgram(int $programVersionId): array;

    public function save(CourseSection $section): int;
}
