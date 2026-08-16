<?php

namespace AMToolkit\Modules\Courses\Services;

use AMToolkit\Core\Diagnostics\RequestId;
use AMToolkit\Modules\Courses\Contracts\CourseEntitlementGateway;
use AMToolkit\Modules\Courses\Contracts\ProductCourseMappingStore;
use AMToolkit\Modules\Courses\Domain\CourseAccessSource;
use AMToolkit\Modules\Courses\Domain\PurchaseAccessRecord;

defined('ABSPATH') || exit;

final class CourseAccessLifecycle
{
    public function __construct(
        private ProductCourseMappingStore $mappings,
        private CourseEntitlementGateway $entitlements
    ) {
    }

    /**
     * @param list<int> $productIds
     * @param array<string, mixed> $metadata
     * @return list<int>|\WP_Error
     */
    public function grantPurchase(
        int $userId,
        int $orderId,
        array $productIds,
        array $metadata = [],
        ?string $requestId = null
    ): array|\WP_Error {
        return $this->grantMappedSource(
            $userId,
            CourseAccessSource::PURCHASE,
            $orderId,
            $productIds,
            $metadata,
            $requestId
        );
    }

    /** @return list<int>|\WP_Error */
    public function grantPurchaseRecord(
        PurchaseAccessRecord $purchase,
        ?string $requestId = null
    ): array|\WP_Error {
        return $this->grantPurchase(
            $purchase->userId(),
            $purchase->orderId(),
            $purchase->productIds(),
            $purchase->metadata(),
            $requestId
        );
    }

    public function revokePurchase(int $orderId, ?string $requestId = null): int|\WP_Error
    {
        if ($orderId <= 0) {
            return new \WP_Error(
                'am_toolkit_invalid_course_access_source',
                __('Nieprawidłowe zamówienie źródłowe dostępu do kursu.', 'am-toolkit')
            );
        }

        return $this->entitlements->revokeAllSource(
            CourseAccessSource::PURCHASE,
            $orderId,
            ['request_id' => RequestId::normalize($requestId)]
        );
    }

    /**
     * @param list<int> $productIds
     * @param array<string, mixed> $metadata
     * @return list<int>|\WP_Error
     */
    public function activateSubscription(
        int $userId,
        int $subscriptionId,
        array $productIds,
        array $metadata = [],
        ?string $requestId = null
    ): array|\WP_Error {
        return $this->grantMappedSource(
            $userId,
            CourseAccessSource::SUBSCRIPTION,
            $subscriptionId,
            $productIds,
            $metadata,
            $requestId
        );
    }

    public function endSubscription(
        int $subscriptionId,
        ?string $requestId = null
    ): int|\WP_Error {
        return $this->entitlements->revokeAllSource(
            CourseAccessSource::SUBSCRIPTION,
            $subscriptionId,
            ['request_id' => RequestId::normalize($requestId)]
        );
    }

    /** @param array<string, mixed> $metadata */
    public function grantManual(
        int $userId,
        int $courseId,
        int $assignmentId,
        array $metadata = [],
        ?string $requestId = null
    ): int|\WP_Error {
        return $this->grantDirectSource(
            $userId,
            $courseId,
            CourseAccessSource::MANUAL,
            $assignmentId,
            $metadata,
            $requestId
        );
    }

    public function revokeManual(int $assignmentId, ?string $requestId = null): int|\WP_Error
    {
        return $this->entitlements->revokeAllSource(
            CourseAccessSource::MANUAL,
            $assignmentId,
            ['request_id' => RequestId::normalize($requestId)]
        );
    }

    /** @param list<int> $lessonIds */
    public function grantDemo(
        int $userId,
        int $courseId,
        int $demoId,
        array $lessonIds,
        ?string $requestId = null
    ): int|\WP_Error {
        $lessonIds = array_values(array_unique(array_filter(
            array_map('absint', $lessonIds),
            static fn (int $id): bool => $id > 0
        )));

        return $this->grantDirectSource(
            $userId,
            $courseId,
            CourseAccessSource::DEMO,
            $demoId,
            ['lesson_ids' => $lessonIds, 'state' => 'active'],
            $requestId
        );
    }

    /**
     * @param list<int> $productIds
     * @param array<string, mixed> $metadata
     * @return list<int>|\WP_Error
     */
    private function grantMappedSource(
        int $userId,
        string $sourceType,
        int $sourceId,
        array $productIds,
        array $metadata,
        ?string $requestId
    ): array|\WP_Error {
        if ($userId <= 0 || $sourceId <= 0) {
            return new \WP_Error(
                'am_toolkit_invalid_course_access_source',
                __('Nieprawidłowy użytkownik lub źródło dostępu do kursu.', 'am-toolkit')
            );
        }

        $productIds = array_values(array_unique(array_filter(
            array_map('absint', $productIds),
            static fn (int $id): bool => $id > 0
        )));
        $requestId = RequestId::normalize($requestId);
        $grantIds = [];
        $courseIds = $this->mappings->courseIdsForProducts($productIds);

        if (is_wp_error($courseIds)) {
            return $courseIds;
        }

        foreach ($courseIds as $courseId) {
            $grant = $this->grantDirectSource(
                $userId,
                $courseId,
                $sourceType,
                $sourceId,
                ['product_ids' => $productIds] + $metadata,
                $requestId
            );

            if (is_wp_error($grant)) {
                return $grant;
            }

            $grantIds[] = $grant;
        }

        return $grantIds;
    }

    /** @param array<string, mixed> $metadata */
    private function grantDirectSource(
        int $userId,
        int $courseId,
        string $sourceType,
        int $sourceId,
        array $metadata,
        ?string $requestId
    ): int|\WP_Error {
        if ($userId <= 0 || $courseId <= 0 || $sourceId <= 0) {
            return new \WP_Error(
                'am_toolkit_invalid_course_access_source',
                __('Nieprawidłowy użytkownik, kurs lub źródło dostępu.', 'am-toolkit')
            );
        }

        return $this->entitlements->grant(
            $userId,
            $courseId,
            [
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'metadata' => $metadata,
                'request_id' => RequestId::normalize($requestId),
            ]
        );
    }
}
