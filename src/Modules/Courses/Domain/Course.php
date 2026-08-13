<?php

namespace AMToolkit\Modules\Courses\Domain;

defined('ABSPATH') || exit;

final class Course
{
    public function __construct(
        private int $id,
        private Identifier $publicId,
        private string $title,
        private string $description,
        private string $status,
        private ?int $currentProgramVersionId = null,
        private ?string $archivedAt = null
    ) {
        if ($id < 0) {
            throw new \InvalidArgumentException('Course ID cannot be negative.');
        }

        if (trim($title) === '') {
            throw new \InvalidArgumentException('Course title cannot be empty.');
        }

        PublicationStatus::assertValid($status);

        if ($currentProgramVersionId !== null && $currentProgramVersionId <= 0) {
            throw new \InvalidArgumentException('Current program version ID must be positive.');
        }

        if (($status === PublicationStatus::ARCHIVED) !== ($archivedAt !== null)) {
            throw new \InvalidArgumentException('Archived course status and timestamp must be set together.');
        }
    }

    public function id(): int
    {
        return $this->id;
    }

    public function publicId(): Identifier
    {
        return $this->publicId;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function description(): string
    {
        return $this->description;
    }

    public function status(): string
    {
        return $this->status;
    }

    public function currentProgramVersionId(): ?int
    {
        return $this->currentProgramVersionId;
    }

    public function archivedAt(): ?string
    {
        return $this->archivedAt;
    }
}
