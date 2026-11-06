<?php

declare(strict_types=1);

use AMToolkit\Modules\Courses\Frontend\CoursePlayerMode;
use PHPUnit\Framework\TestCase;

final class CoursePlayerModeTest extends TestCase
{
    /** @dataProvider operatorOverrides */
    public function testItValidatesOperatorOverrides(mixed $override, string $expected): void
    {
        self::assertSame($expected, CoursePlayerMode::normalizeDefault($override));
    }

    public function operatorOverrides(): array
    {
        return [
            'native' => ['native', 'native'],
            'mediaelement' => ['mediaelement', 'mediaelement'],
            'missing' => [null, 'native'],
            'empty' => ['', 'native'],
            'unknown' => ['unknown', 'native'],
            'uppercase' => ['MEDIAELEMENT', 'native'],
            'array' => [['mediaelement'], 'native'],
            'boolean' => [true, 'native'],
        ];
    }

    /** @dataProvider diagnosticRequests */
    public function testExplicitDiagnosticModeOverridesTheDefault(
        bool $requested,
        mixed $mode,
        mixed $operatorOverride,
        string $expected
    ): void {
        self::assertSame(
            $expected,
            CoursePlayerMode::normalizeDiagnosticRequest(
                $requested,
                $mode,
                $operatorOverride
            )
        );
    }

    public function diagnosticRequests(): array
    {
        return [
            'native overrides MediaElement default' => [true, 'native', 'mediaelement', 'native'],
            'MediaElement overrides native default' => [true, 'mediaelement', 'native', 'mediaelement'],
            'query without diagnostics uses native default' => [false, 'mediaelement', 'native', 'native'],
            'query without diagnostics uses operator override' => [false, 'native', 'mediaelement', 'mediaelement'],
            'invalid diagnostic mode uses operator override' => [true, 'unknown', 'mediaelement', 'mediaelement'],
            'invalid operator override remains native' => [true, 'unknown', ['mediaelement'], 'native'],
        ];
    }
}
