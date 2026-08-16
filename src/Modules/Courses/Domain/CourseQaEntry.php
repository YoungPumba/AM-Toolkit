<?php

namespace AMToolkit\Modules\Courses\Domain;

defined('ABSPATH') || exit;

final class CourseQaEntry
{
    public function __construct(
        private int $id,
        private ?Identifier $publicId,
        private int $courseId,
        private ?int $lessonId,
        private string $question,
        private string $answer,
        private int $position,
        private string $status
    ) {
        PublicationStatus::assertValid($status);

        if (
            $id < 0
            || $courseId <= 0
            || ($lessonId !== null && $lessonId <= 0)
            || trim($question) === ''
            || trim($answer) === ''
            || $position < 0
        ) {
            throw new \InvalidArgumentException('Course Q&A entry is invalid.');
        }
    }

    public function id(): int { return $this->id; }
    public function publicId(): ?Identifier { return $this->publicId; }
    public function courseId(): int { return $this->courseId; }
    public function lessonId(): ?int { return $this->lessonId; }
    public function question(): string { return $this->question; }
    public function answer(): string { return $this->answer; }
    public function position(): int { return $this->position; }
    public function status(): string { return $this->status; }
}
