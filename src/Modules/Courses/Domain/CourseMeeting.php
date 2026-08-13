<?php

namespace AMToolkit\Modules\Courses\Domain;

defined('ABSPATH') || exit;

final class CourseMeeting
{
    private \DateTimeImmutable $startsAtUtc;

    private \DateTimeImmutable $endsAtUtc;

    public function __construct(
        private int $id,
        private Identifier $publicId,
        private int $courseId,
        private string $title,
        \DateTimeInterface $startsAt,
        \DateTimeInterface $endsAt,
        private string $displayTimezone,
        private string $platform,
        private ?string $joinReference,
        private ?string $recordingReference,
        private string $status
    ) {
        if ($id < 0 || $courseId <= 0 || trim($title) === '') {
            throw new \InvalidArgumentException('Meeting identifiers and title are invalid.');
        }

        try {
            new \DateTimeZone($displayTimezone);
        } catch (\Throwable) {
            throw new \InvalidArgumentException('Meeting display timezone is invalid.');
        }

        PublicationStatus::assertValid($status);
        $utc = new \DateTimeZone('UTC');
        $this->startsAtUtc = \DateTimeImmutable::createFromInterface($startsAt)->setTimezone($utc);
        $this->endsAtUtc = \DateTimeImmutable::createFromInterface($endsAt)->setTimezone($utc);

        if ($this->endsAtUtc <= $this->startsAtUtc) {
            throw new \InvalidArgumentException('Meeting end must be later than its start.');
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

    public function startsAtUtc(): \DateTimeImmutable
    {
        return $this->startsAtUtc;
    }

    public function endsAtUtc(): \DateTimeImmutable
    {
        return $this->endsAtUtc;
    }

    public function displayTimezone(): string
    {
        return $this->displayTimezone;
    }

    public function platform(): string
    {
        return $this->platform;
    }

    public function joinReference(): ?string
    {
        return $this->joinReference;
    }

    public function recordingReference(): ?string
    {
        return $this->recordingReference;
    }

    public function status(): string
    {
        return $this->status;
    }
}
