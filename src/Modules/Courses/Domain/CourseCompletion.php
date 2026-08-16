<?php

namespace AMToolkit\Modules\Courses\Domain;

defined('ABSPATH') || exit;

final class CourseCompletion
{
    /** @var list<int> */
    private array $requiredLessonIds;

    /** @param list<int> $requiredLessonIds */
    public function __construct(
        private int $id,
        private int $userId,
        private int $courseId,
        private int $programVersionId,
        array $requiredLessonIds,
        private string $completionSource,
        private string $completedAt,
        private ?string $requestId = null
    ) {
        if ($id < 0 || $userId <= 0 || $courseId <= 0 || $programVersionId <= 0) {
            throw new \InvalidArgumentException('Course completion identifiers are invalid.');
        }

        if (trim($completionSource) === '' || trim($completedAt) === '') {
            throw new \InvalidArgumentException('Course completion source and timestamp are required.');
        }

        foreach ($requiredLessonIds as $lessonId) {
            if ($lessonId <= 0) {
                throw new \InvalidArgumentException('Required lesson IDs must be positive.');
            }
        }

        if (count(array_unique($requiredLessonIds)) !== count($requiredLessonIds)) {
            throw new \InvalidArgumentException('Required lesson snapshot cannot contain duplicates.');
        }

        sort($requiredLessonIds, SORT_NUMERIC);
        $this->requiredLessonIds = $requiredLessonIds;
    }

    public function id(): int
    {
        return $this->id;
    }

    public function userId(): int
    {
        return $this->userId;
    }

    public function courseId(): int
    {
        return $this->courseId;
    }

    public function programVersionId(): int
    {
        return $this->programVersionId;
    }

    /** @return list<int> */
    public function requiredLessonIds(): array
    {
        return $this->requiredLessonIds;
    }

    public function requirementsHash(): string
    {
        return hash('sha256', implode(',', $this->requiredLessonIds));
    }

    public function completionSource(): string
    {
        return $this->completionSource;
    }

    public function completedAt(): string
    {
        return $this->completedAt;
    }

    public function requestId(): ?string
    {
        return $this->requestId;
    }
}
