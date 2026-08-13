<?php

namespace AMToolkit\Modules\Courses\Contracts;

use AMToolkit\Modules\Courses\Domain\CourseProgramVersion;

defined('ABSPATH') || exit;

interface ProgramRepository
{
    public function find(int $programVersionId): ?CourseProgramVersion;

    public function findPublishedForCourse(int $courseId): ?CourseProgramVersion;

    public function save(CourseProgramVersion $program): int;
}
