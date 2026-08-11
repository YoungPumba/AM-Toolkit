<?php

namespace AMToolkit\Core\Diagnostics;

defined('ABSPATH') || exit;

final class WpTechnicalLogger implements TechnicalLogger
{
    public function error(string $message, array $context = []): void
    {
        $safeContext = array_intersect_key(
            $context,
            array_flip([
                'request_id',
                'error_code',
                'event_type',
                'operation',
                'object_type',
                'object_id',
            ])
        );

        if (function_exists('wc_get_logger')) {
            wc_get_logger()->error(
                $message,
                ['source' => 'am-toolkit'] + $safeContext
            );

            return;
        }

        error_log(
            '[AM Toolkit] ' . $message . ' ' . (string) wp_json_encode($safeContext)
        );
    }
}
