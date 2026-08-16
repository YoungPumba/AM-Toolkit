<?php

namespace AMToolkit\Modules\Courses\Contracts;

use AMToolkit\Modules\Courses\Domain\Identifier;

defined('ABSPATH') || exit;

interface CourseProgressSourceStore
{
    /** @return array<string, mixed>|null|\WP_Error */
    public function lessonContext(Identifier $coursePublicId, Identifier $lessonPublicId): array|null|\WP_Error;

    /**
     * @param list<array{0: float, 1: float}> $intervals
     * @return bool|\WP_Error True only when a new source checkpoint was inserted.
     */
    public function recordVideoCheckpoint(
        int $userId,
        int $courseId,
        int $lessonId,
        int $contentVersion,
        string $requestId,
        array $intervals,
        int $durationSeconds,
        float $coveredSeconds,
        string $occurredAt
    ): bool|\WP_Error;

    /** @return list<list<array{0: float, 1: float}>>|\WP_Error */
    public function videoCheckpointIntervals(int $userId, int $lessonId, int $contentVersion): array|\WP_Error;

    /** @return bool|\WP_Error True only when a new requirement completion was inserted. */
    public function recordRequirementCompletion(
        int $userId,
        int $courseId,
        int $lessonId,
        int $contentVersion,
        string $requirementKey,
        string $completionSource,
        string $requestId,
        string $completedAt
    ): bool|\WP_Error;

    public function hasRequirementCompletion(
        int $userId,
        int $lessonId,
        int $contentVersion,
        string $requirementKey
    ): bool|\WP_Error;
}
