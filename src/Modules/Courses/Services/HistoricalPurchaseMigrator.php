<?php

namespace AMToolkit\Modules\Courses\Services;

use AMToolkit\Core\Diagnostics\RequestId;
use AMToolkit\Modules\Courses\Contracts\HistoricalPurchaseSource;
use AMToolkit\Modules\Courses\Contracts\MigrationCheckpointStore;

defined('ABSPATH') || exit;

final class HistoricalPurchaseMigrator
{
    public function __construct(
        private HistoricalPurchaseSource $source,
        private MigrationCheckpointStore $checkpoint,
        private CourseAccessLifecycle $access
    ) {
    }

    /**
     * @return array{processed: int, grants: int, next_page: int, completed: bool}|\WP_Error
     */
    public function runBatch(int $limit = 50): array|\WP_Error
    {
        $limit = min(200, max(1, $limit));
        $state = $this->checkpoint->load();

        if ($state['completed']) {
            return [
                'processed' => 0,
                'grants' => 0,
                'next_page' => $state['page'],
                'completed' => true,
            ];
        }

        $batch = $this->source->fetch($state['page'], $limit);

        if (is_wp_error($batch)) {
            return $batch;
        }

        $grants = 0;

        foreach ($batch->records() as $purchase) {
            $result = $this->access->grantPurchaseRecord(
                $purchase,
                RequestId::generate()
            );

            if (is_wp_error($result)) {
                return $result;
            }

            $grants += count($result);
        }

        $nextPage = $state['page'] + 1;
        $completed = !$batch->hasMore();

        if (!$this->checkpoint->save($nextPage, $completed)) {
            return new \WP_Error(
                'am_toolkit_course_backfill_checkpoint_failed',
                __('Nie udało się zapisać punktu wznowienia migracji dostępów.', 'am-toolkit')
            );
        }

        return [
            'processed' => count($batch->records()),
            'grants' => $grants,
            'next_page' => $nextPage,
            'completed' => $completed,
        ];
    }
}
