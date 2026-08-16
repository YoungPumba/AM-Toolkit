<?php

namespace AMToolkit\Modules\Courses\Domain;

defined('ABSPATH') || exit;

final class LessonCompletionRequirements
{
    public function __construct(
        private int $videoPercent,
        private bool $taskRequired
    ) {
        if ($videoPercent < 0 || $videoPercent > 100) {
            throw new \InvalidArgumentException('Required video percentage must be between 0 and 100.');
        }
    }

    /** @param array<string, mixed> $requirements */
    public static function fromArray(array $requirements): self
    {
        return new self(
            min(100, max(0, (int) ($requirements['video_percent'] ?? 0))),
            !empty($requirements['task_required'])
        );
    }

    public function videoPercent(): int
    {
        return $this->videoPercent;
    }

    public function taskRequired(): bool
    {
        return $this->taskRequired;
    }

    public function hasAutomaticRequirements(): bool
    {
        return $this->videoPercent > 0 || $this->taskRequired;
    }

    public function isSatisfied(float $watchedPercent, bool $taskCompleted): bool
    {
        return ($this->videoPercent === 0 || $watchedPercent >= $this->videoPercent)
            && (!$this->taskRequired || $taskCompleted);
    }
}
