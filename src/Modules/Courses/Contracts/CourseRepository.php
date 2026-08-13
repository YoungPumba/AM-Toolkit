<?php

namespace AMToolkit\Modules\Courses\Contracts;

use AMToolkit\Modules\Courses\Domain\Course;
use AMToolkit\Modules\Courses\Domain\Identifier;

defined('ABSPATH') || exit;

interface CourseRepository
{
    public function find(int $courseId): ?Course;

    public function findByPublicId(Identifier $publicId): ?Course;

    public function save(Course $course): int;
}
