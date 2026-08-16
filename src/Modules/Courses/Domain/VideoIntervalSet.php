<?php

namespace AMToolkit\Modules\Courses\Domain;

defined('ABSPATH') || exit;

final class VideoIntervalSet
{
    private const PRECISION = 3;

    /** @var list<array{0: float, 1: float}> */
    private array $intervals;

    /**
     * @param array<int, mixed> $intervals
     */
    public function __construct(array $intervals, float $durationSeconds)
    {
        if (!is_finite($durationSeconds) || $durationSeconds <= 0) {
            throw new \InvalidArgumentException('Video duration must be a positive finite number.');
        }

        $normalized = [];

        foreach ($intervals as $interval) {
            if (
                !is_array($interval)
                || count($interval) !== 2
                || !isset($interval[0], $interval[1])
                || !is_numeric($interval[0])
                || !is_numeric($interval[1])
            ) {
                throw new \InvalidArgumentException('Every watched interval needs a numeric start and end.');
            }

            $start = max(0.0, min($durationSeconds, (float) $interval[0]));
            $end = max(0.0, min($durationSeconds, (float) $interval[1]));

            if (!is_finite($start) || !is_finite($end) || $end <= $start) {
                continue;
            }

            $normalized[] = [$start, $end];
        }

        usort($normalized, static fn (array $left, array $right): int => $left[0] <=> $right[0]);
        $this->intervals = $this->merge($normalized);
    }

    /**
     * @param list<self> $sets
     */
    public static function combine(array $sets, float $durationSeconds): self
    {
        $intervals = [];

        foreach ($sets as $set) {
            foreach ($set->intervals() as $interval) {
                $intervals[] = $interval;
            }
        }

        return new self($intervals, $durationSeconds);
    }

    /** @return list<array{0: float, 1: float}> */
    public function intervals(): array
    {
        return $this->intervals;
    }

    public function coveredSeconds(): float
    {
        $covered = 0.0;

        foreach ($this->intervals as [$start, $end]) {
            $covered += $end - $start;
        }

        return round($covered, self::PRECISION);
    }

    public function percentage(float $durationSeconds): float
    {
        if ($durationSeconds <= 0) {
            return 0.0;
        }

        return round(min(100.0, ($this->coveredSeconds() / $durationSeconds) * 100), 2);
    }

    /**
     * @param list<array{0: float, 1: float}> $intervals
     * @return list<array{0: float, 1: float}>
     */
    private function merge(array $intervals): array
    {
        $merged = [];

        foreach ($intervals as [$start, $end]) {
            $last = array_key_last($merged);

            if ($last === null || $start > $merged[$last][1] + 0.001) {
                $merged[] = [round($start, self::PRECISION), round($end, self::PRECISION)];
                continue;
            }

            $merged[$last][1] = round(max($merged[$last][1], $end), self::PRECISION);
        }

        return $merged;
    }
}
