<?php

namespace AMToolkit\Modules\Courses\Domain;

defined('ABSPATH') || exit;

final class CourseProgramVersion
{
    /** @var list<int> */
    private array $lessonIds;

    /** @var list<int> */
    private array $requiredLessonIds;

    /**
     * @param list<int> $lessonIds Ordered lesson IDs in this immutable version.
     * @param list<int> $requiredLessonIds Required subset of $lessonIds.
     */
    public function __construct(
        private int $id,
        private Identifier $publicId,
        private int $courseId,
        private int $versionNumber,
        private string $status,
        array $lessonIds,
        array $requiredLessonIds,
        private ?string $publishedAt = null
    ) {
        if ($id < 0 || $courseId <= 0 || $versionNumber <= 0) {
            throw new \InvalidArgumentException('Program identifiers and version must be positive.');
        }

        PublicationStatus::assertValid($status);
        $this->lessonIds = $this->validateIds($lessonIds, 'program lesson');
        $this->requiredLessonIds = $this->validateIds($requiredLessonIds, 'required lesson');

        if (array_diff($this->requiredLessonIds, $this->lessonIds) !== []) {
            throw new \InvalidArgumentException('Required lessons must belong to the program version.');
        }

        if ($status === PublicationStatus::PUBLISHED && $publishedAt === null) {
            throw new \InvalidArgumentException('Published program needs its publication timestamp.');
        }

        if ($status === PublicationStatus::DRAFT && $publishedAt !== null) {
            throw new \InvalidArgumentException('Draft program cannot have a publication timestamp.');
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

    public function versionNumber(): int
    {
        return $this->versionNumber;
    }

    public function status(): string
    {
        return $this->status;
    }

    /** @return list<int> */
    public function lessonIds(): array
    {
        return $this->lessonIds;
    }

    /** @return list<int> */
    public function requiredLessonIds(): array
    {
        return $this->requiredLessonIds;
    }

    public function contentHash(): string
    {
        return hash('sha256', (string) json_encode([
            'lessons' => $this->lessonIds,
            'required' => $this->requiredLessonIds,
        ]));
    }

    public function publishedAt(): ?string
    {
        return $this->publishedAt;
    }

    /**
     * @param list<int> $ids
     * @return list<int>
     */
    private function validateIds(array $ids, string $label): array
    {
        foreach ($ids as $id) {
            if ($id <= 0) {
                throw new \InvalidArgumentException("Every {$label} ID must be positive.");
            }
        }

        if (count(array_unique($ids)) !== count($ids)) {
            throw new \InvalidArgumentException("Duplicate {$label} ID.");
        }

        return $ids;
    }
}
