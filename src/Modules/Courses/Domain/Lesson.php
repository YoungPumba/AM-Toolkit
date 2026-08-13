<?php

namespace AMToolkit\Modules\Courses\Domain;

defined('ABSPATH') || exit;

final class Lesson
{
    /** @param array<string, mixed> $completionRequirements */
    public function __construct(
        private int $id,
        private Identifier $publicId,
        private int $courseId,
        private string $title,
        private string $description,
        private string $status,
        private int $contentVersion,
        private array $completionRequirements = [],
        private ?string $archivedAt = null
    ) {
        if ($id < 0 || $courseId <= 0 || $contentVersion <= 0) {
            throw new \InvalidArgumentException('Lesson identifiers and content version are invalid.');
        }

        if (trim($title) === '') {
            throw new \InvalidArgumentException('Lesson title cannot be empty.');
        }

        PublicationStatus::assertValid($status);

        if (($status === PublicationStatus::ARCHIVED) !== ($archivedAt !== null)) {
            throw new \InvalidArgumentException('Archived lesson status and timestamp must be set together.');
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

    public function courseId(): int
    {
        return $this->courseId;
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

    public function contentVersion(): int
    {
        return $this->contentVersion;
    }

    /** @return array<string, mixed> */
    public function completionRequirements(): array
    {
        return $this->completionRequirements;
    }

    public function archivedAt(): ?string
    {
        return $this->archivedAt;
    }
}
