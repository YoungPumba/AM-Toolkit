<?php

namespace AMToolkit\Modules\Courses\Domain;

defined('ABSPATH') || exit;

final class LessonTask
{
    public function __construct(
        private int $id,
        private ?Identifier $publicId,
        private int $courseId,
        private int $lessonId,
        private string $title,
        private string $description,
        private int $position,
        private bool $required,
        private string $status
    ) {
        PublicationStatus::assertValid($status);

        if ($id < 0 || $courseId <= 0 || $lessonId <= 0 || trim($title) === '' || $position < 0) {
            throw new \InvalidArgumentException('Lesson task is invalid.');
        }
    }

    public function id(): int { return $this->id; }
    public function publicId(): ?Identifier { return $this->publicId; }
    public function courseId(): int { return $this->courseId; }
    public function lessonId(): int { return $this->lessonId; }
    public function title(): string { return $this->title; }
    public function description(): string { return $this->description; }
    public function position(): int { return $this->position; }
    public function required(): bool { return $this->required; }
    public function status(): string { return $this->status; }
}
