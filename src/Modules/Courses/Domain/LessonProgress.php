<?php

namespace AMToolkit\Modules\Courses\Domain;

defined('ABSPATH') || exit;

final class LessonProgress
{
    public function __construct(
        private int $id,
        private int $userId,
        private int $courseId,
        private int $lessonId,
        private string $status,
        private int $contentVersion,
        private ?string $completionSource = null,
        private ?string $completedAt = null,
        private ?string $requestId = null
    ) {
        if ($id < 0 || $userId <= 0 || $courseId <= 0 || $lessonId <= 0 || $contentVersion <= 0) {
            throw new \InvalidArgumentException('Lesson progress identifiers and version are invalid.');
        }

        ProgressStatus::assertValid($status);

        if ($status === ProgressStatus::COMPLETED && ($completionSource === null || $completedAt === null)) {
            throw new \InvalidArgumentException('Completed lesson progress needs its source and timestamp.');
        }

        if ($status !== ProgressStatus::COMPLETED && $completedAt !== null) {
            throw new \InvalidArgumentException('Only completed lesson progress may have completed_at.');
        }
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

    public function lessonId(): int
    {
        return $this->lessonId;
    }

    public function status(): string
    {
        return $this->status;
    }

    public function contentVersion(): int
    {
        return $this->contentVersion;
    }

    public function completionSource(): ?string
    {
        return $this->completionSource;
    }

    public function completedAt(): ?string
    {
        return $this->completedAt;
    }

    public function requestId(): ?string
    {
        return $this->requestId;
    }
}
