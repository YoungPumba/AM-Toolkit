<?php

declare(strict_types=1);

namespace AMToolkit\Tests\Unit;

use AMToolkit\Modules\Courses\Contracts\CourseEntitlementGateway;
use AMToolkit\Modules\Courses\Contracts\HistoricalPurchaseSource;
use AMToolkit\Modules\Courses\Contracts\MigrationCheckpointStore;
use AMToolkit\Modules\Courses\Contracts\ProductCourseMappingStore;
use AMToolkit\Modules\Courses\Domain\CourseAccessSource;
use AMToolkit\Modules\Courses\Domain\PurchaseAccessRecord;
use AMToolkit\Modules\Courses\Domain\PurchaseBatch;
use AMToolkit\Modules\Courses\Domain\SubscriptionStatusPolicy;
use AMToolkit\Modules\Courses\Services\CourseAccessLifecycle;
use AMToolkit\Modules\Courses\Services\HistoricalPurchaseMigrator;
use PHPUnit\Framework\TestCase;

final class CourseAccessLifecycleTest extends TestCase
{
    private MemoryCourseMappingStore $mappings;
    private MemoryCourseEntitlementGateway $entitlements;
    private CourseAccessLifecycle $lifecycle;

    protected function setUp(): void
    {
        $this->mappings = new MemoryCourseMappingStore([101 => [7, 8]]);
        $this->entitlements = new MemoryCourseEntitlementGateway();
        $this->lifecycle = new CourseAccessLifecycle($this->mappings, $this->entitlements);
    }

    public function testRepeatedPurchaseUsesTheSameGrantsAndRequestId(): void
    {
        $requestId = 'AM-20260814-ABCDEF123456';

        $first = $this->lifecycle->grantPurchase(5, 900, [101], [], $requestId);
        $second = $this->lifecycle->grantPurchase(5, 900, [101], [], $requestId);

        self::assertSame([1, 2], $first);
        self::assertSame([1, 2], $second);
        self::assertCount(2, $this->entitlements->grants);
        self::assertSame(
            [$requestId, $requestId, $requestId, $requestId],
            array_column($this->entitlements->grantCalls, 'request_id')
        );
    }

    public function testEndingSubscriptionDoesNotRemovePurchaseSource(): void
    {
        $this->lifecycle->grantPurchase(5, 900, [101]);
        $this->lifecycle->activateSubscription(5, 300, [101]);

        self::assertSame(2, $this->lifecycle->endSubscription(300));
        self::assertTrue($this->entitlements->hasActive(5, 7));
        self::assertTrue($this->entitlements->hasActive(5, 8));
        self::assertCount(2, $this->entitlements->activeBySource(CourseAccessSource::PURCHASE, 900));
        self::assertCount(0, $this->entitlements->activeBySource(CourseAccessSource::SUBSCRIPTION, 300));
    }

    public function testSubscriptionCanBeRestoredAfterItWasEnded(): void
    {
        self::assertSame([1, 2], $this->lifecycle->activateSubscription(5, 300, [101]));
        self::assertSame(2, $this->lifecycle->endSubscription(300));
        self::assertSame([1, 2], $this->lifecycle->activateSubscription(5, 300, [101]));
        self::assertCount(2, $this->entitlements->activeBySource(CourseAccessSource::SUBSCRIPTION, 300));
    }

    public function testManualRevokeTargetsOnlyTheSelectedAssignment(): void
    {
        $this->lifecycle->grantManual(5, 7, 44);
        $this->lifecycle->grantManual(5, 7, 45);

        self::assertSame(1, $this->lifecycle->revokeManual(44));
        self::assertTrue($this->entitlements->hasActive(5, 7));
        self::assertCount(1, $this->entitlements->activeBySource(CourseAccessSource::MANUAL, 45));
    }

    public function testDemoGrantCarriesAnExplicitLessonScope(): void
    {
        $id = $this->lifecycle->grantDemo(5, 7, 80, [12, 10, 12, 0]);

        self::assertSame(1, $id);
        self::assertSame([12, 10], $this->entitlements->grants[1]['metadata']['lesson_ids']);
        self::assertSame('active', $this->entitlements->grants[1]['metadata']['state']);
    }

    public function testHistoricalMigrationResumesAtTheNextCompletedPage(): void
    {
        $source = new MemoryHistoricalPurchaseSource([
            1 => new PurchaseBatch([new PurchaseAccessRecord(5, 900, [101])], true),
            2 => new PurchaseBatch([new PurchaseAccessRecord(6, 901, [101])], false),
        ]);
        $checkpoint = new MemoryMigrationCheckpointStore();
        $migrator = new HistoricalPurchaseMigrator($source, $checkpoint, $this->lifecycle);

        self::assertSame(2, $migrator->runBatch(10)['next_page']);
        $last = $migrator->runBatch(10);

        self::assertTrue($last['completed']);
        self::assertSame([1, 2], $source->requestedPages);
        self::assertCount(4, $this->entitlements->grants);

        $again = $migrator->runBatch(10);
        self::assertSame(0, $again['processed']);
        self::assertSame([1, 2], $source->requestedPages);
    }

    public function testSubscriptionStatusesHaveAnExplicitAccessPolicy(): void
    {
        $policy = new SubscriptionStatusPolicy();

        self::assertSame(SubscriptionStatusPolicy::GRANT, $policy->actionFor('active'));
        self::assertSame(SubscriptionStatusPolicy::RETAIN, $policy->actionFor('pending-cancel'));
        self::assertSame(SubscriptionStatusPolicy::REVOKE, $policy->actionFor('on-hold'));
        self::assertSame(SubscriptionStatusPolicy::REVOKE, $policy->actionFor('cancelled'));
        self::assertSame(SubscriptionStatusPolicy::REVOKE, $policy->actionFor('expired'));
        self::assertSame(SubscriptionStatusPolicy::IGNORE, $policy->actionFor('custom-review'));
    }

    public function testMappingReadFailureDoesNotPretendThatNothingIsMapped(): void
    {
        $this->mappings->failure = new \WP_Error('mapping_read_failed', 'Database unavailable');

        $result = $this->lifecycle->grantPurchase(5, 900, [101]);

        self::assertInstanceOf(\WP_Error::class, $result);
        self::assertSame('mapping_read_failed', $result->get_error_code());
        self::assertCount(0, $this->entitlements->grants);
    }
}

final class MemoryCourseMappingStore implements ProductCourseMappingStore
{
    public ?\WP_Error $failure = null;

    /** @param array<int, list<int>> $map */
    public function __construct(private array $map)
    {
    }

    public function map(int $productId, int $courseId): bool|\WP_Error
    {
        $this->map[$productId] ??= [];
        $this->map[$productId][] = $courseId;
        return true;
    }

    public function unmap(int $productId, int $courseId): bool|\WP_Error
    {
        $this->map[$productId] = array_values(array_diff($this->map[$productId] ?? [], [$courseId]));
        return true;
    }

    public function courseIdsForProducts(array $productIds): array|\WP_Error
    {
        if ($this->failure !== null) {
            return $this->failure;
        }

        $courses = [];
        foreach ($productIds as $productId) {
            $courses = array_merge($courses, $this->map[$productId] ?? []);
        }
        return array_values(array_unique($courses));
    }

    public function productIdsForCourse(int $courseId): array|\WP_Error
    {
        if ($this->failure !== null) {
            return $this->failure;
        }

        $products = [];
        foreach ($this->map as $productId => $courseIds) {
            if (in_array($courseId, $courseIds, true)) {
                $products[] = $productId;
            }
        }

        sort($products);

        return $products;
    }
}

final class MemoryCourseEntitlementGateway implements CourseEntitlementGateway
{
    /** @var array<int, array<string, mixed>> */
    public array $grants = [];

    /** @var list<array<string, mixed>> */
    public array $grantCalls = [];

    public function grant(int $userId, int $courseId, array $context): int|\WP_Error
    {
        $key = implode('|', [$userId, $courseId, $context['source_type'], $context['source_id']]);
        $this->grantCalls[] = $context;

        foreach ($this->grants as $id => &$grant) {
            if ($grant['key'] === $key) {
                $grant['active'] = true;
                return $id;
            }
        }

        $id = count($this->grants) + 1;
        $this->grants[$id] = [
            'key' => $key,
            'user_id' => $userId,
            'course_id' => $courseId,
            'source_type' => $context['source_type'],
            'source_id' => $context['source_id'],
            'metadata' => $context['metadata'],
            'active' => true,
        ];
        return $id;
    }

    public function revokeAllSource(string $sourceType, int $sourceId, array $context): int|\WP_Error
    {
        $revoked = 0;
        foreach ($this->grants as &$grant) {
            if ($grant['source_type'] === $sourceType && $grant['source_id'] === $sourceId && $grant['active']) {
                $grant['active'] = false;
                ++$revoked;
            }
        }
        return $revoked;
    }

    public function hasActive(int $userId, int $courseId): bool
    {
        foreach ($this->grants as $grant) {
            if ($grant['user_id'] === $userId && $grant['course_id'] === $courseId && $grant['active']) {
                return true;
            }
        }
        return false;
    }

    /** @return list<array<string, mixed>> */
    public function activeBySource(string $sourceType, int $sourceId): array
    {
        return array_values(array_filter(
            $this->grants,
            static fn (array $grant): bool => $grant['source_type'] === $sourceType
                && $grant['source_id'] === $sourceId
                && $grant['active']
        ));
    }
}

final class MemoryHistoricalPurchaseSource implements HistoricalPurchaseSource
{
    /** @var list<int> */
    public array $requestedPages = [];

    /** @param array<int, PurchaseBatch> $pages */
    public function __construct(private array $pages)
    {
    }

    public function fetch(int $page, int $limit): PurchaseBatch|\WP_Error
    {
        $this->requestedPages[] = $page;
        return $this->pages[$page] ?? new PurchaseBatch([], false);
    }
}

final class MemoryMigrationCheckpointStore implements MigrationCheckpointStore
{
    private int $page = 1;
    private bool $completed = false;

    public function load(): array
    {
        return ['page' => $this->page, 'completed' => $this->completed];
    }

    public function save(int $nextPage, bool $completed): bool
    {
        $this->page = $nextPage;
        $this->completed = $completed;
        return true;
    }
}
