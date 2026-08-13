<?php

namespace AMToolkit\Modules\Courses\Domain;

defined('ABSPATH') || exit;

final class CourseSection
{
    public function __construct(
        private int $id,
        private Identifier $publicId,
        private int $programVersionId,
        private string $title,
        private string $description,
        private int $position,
        private string $status,
        private ?string $archivedAt = null
    ) {
        if ($id < 0 || $programVersionId <= 0 || $position < 0) {
            throw new \InvalidArgumentException('Section identifiers and position are invalid.');
        }

        if (trim($title) === '') {
            throw new \InvalidArgumentException('Section title cannot be empty.');
        }

        PublicationStatus::assertValid($status);

        if (($status === PublicationStatus::ARCHIVED) !== ($archivedAt !== null)) {
            throw new \InvalidArgumentException('Archived section status and timestamp must be set together.');
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

    public function programVersionId(): int
    {
        return $this->programVersionId;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function description(): string
    {
        return $this->description;
    }

    public function position(): int
    {
        return $this->position;
    }

    public function status(): string
    {
        return $this->status;
    }

    public function archivedAt(): ?string
    {
        return $this->archivedAt;
    }
}
