<?php

namespace AMToolkit\Modules\Courses\Frontend;

defined('ABSPATH') || exit;

final class CoursePlayerMode
{
    public const NATIVE = 'native';
    public const MEDIAELEMENT = 'mediaelement';

    /**
     * Resolve the operator override without reading global state.
     *
     * @param mixed $operatorOverride
     */
    public static function normalizeDefault(mixed $operatorOverride): string
    {
        return self::isValid($operatorOverride)
            ? (string) $operatorOverride
            : self::NATIVE;
    }

    public static function defaultMode(): string
    {
        return self::normalizeDefault(self::operatorOverride());
    }

    /** @param mixed $mode */
    public static function resolve(mixed $mode): string
    {
        return self::isValid($mode)
            ? (string) $mode
            : self::defaultMode();
    }

    /** @param mixed $requestedMode */
    public static function forDiagnosticRequest(bool $diagnosticsRequested, mixed $requestedMode): string
    {
        return self::normalizeDiagnosticRequest(
            $diagnosticsRequested,
            $requestedMode,
            self::operatorOverride()
        );
    }

    /**
     * Resolve a diagnostic selection without reading global state.
     *
     * @param mixed $requestedMode
     * @param mixed $operatorOverride
     */
    public static function normalizeDiagnosticRequest(
        bool $diagnosticsRequested,
        mixed $requestedMode,
        mixed $operatorOverride
    ): string {
        if ($diagnosticsRequested && self::isValid($requestedMode)) {
            return (string) $requestedMode;
        }

        return self::normalizeDefault($operatorOverride);
    }

    private static function operatorOverride(): mixed
    {
        return defined('AM_TOOLKIT_COURSE_PLAYER_MODE')
            ? constant('AM_TOOLKIT_COURSE_PLAYER_MODE')
            : null;
    }

    private static function isValid(mixed $mode): bool
    {
        return is_string($mode)
            && in_array($mode, [self::NATIVE, self::MEDIAELEMENT], true);
    }
}
