<?php

namespace AMToolkit\Modules\Courses\Domain;

defined('ABSPATH') || exit;

final class LessonMaterial
{
    public function __construct(
        private int $id,
        private Identifier $publicId,
        private int $lessonId,
        private string $name,
        private string $description,
        private string $storageProvider,
        private string $storageReference,
        private int $position,
        private string $status,
        private ?string $archivedAt = null
    ) {
        if ($id < 0 || $lessonId <= 0 || $position < 0) {
            throw new \InvalidArgumentException('Material identifiers and position are invalid.');
        }

        if (trim($name) === '' || trim($storageProvider) === '' || trim($storageReference) === '') {
            throw new \InvalidArgumentException('Material name and storage reference cannot be empty.');
        }

        PublicationStatus::assertValid($status);

        if (($status === PublicationStatus::ARCHIVED) !== ($archivedAt !== null)) {
            throw new \InvalidArgumentException('Archived material status and timestamp must be set together.');
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

    public function lessonId(): int
    {
        return $this->lessonId;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function description(): string
    {
        return $this->description;
    }

    public function storageProvider(): string
    {
        return $this->storageProvider;
    }

    public function storageReference(): string
    {
        return $this->storageReference;
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
