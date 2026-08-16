<?php

namespace AMToolkit\Modules\Courses\Contracts;

defined('ABSPATH') || exit;

/**
 * Optional persistence capability for conservative hard deletion of unused drafts.
 *
 * Implementations must re-check every safety condition in the same request that
 * performs the delete. A UI confirmation is never an authorization boundary.
 */
interface DraftCourseResourceDeletionStore
{
    public function deleteDraftResource(string $resourceType, int $resourceId, int $courseId): bool|\WP_Error;
}
