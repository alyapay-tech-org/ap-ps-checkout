<?php

namespace AlyaPay\Tests\Unit;

use AlyaPayConfig;
use AlyaPayOrderHelper;
use AlyaPayReconcilePendingOrders;
use AlyaPayVendorTransactionService;
use Order;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class AlyaPayReconcilePendingOrdersTest extends TestCase
{
    private const OLD_ENOUGH = '-1 hour';

    /** @var AlyaPayOrderHelper&MockObject */
    private $orderHelper;

    /** @var AlyaPayVendorTransactionService&MockObject */
    private $vendorTransactionService;

    /** @var AlyaPayConfig&MockObject */
    private $config;

    private AlyaPayReconcilePendingOrders $job;

    protected function setUp(): void
    {
        $this->orderHelper = $this->createMock(AlyaPayOrderHelper::class);
        $this->vendorTransactionService = $this->createMock(AlyaPayVendorTransactionService::class);
        $this->config = $this->createMock(AlyaPayConfig::class);

        $this->config->method('isActive')->willReturn(true);
        $this->config->method('getApiKey')->willReturn('test-key');
        $this->config->method('acquireReconcileLock')->willReturn(true);
        $this->config->method('getTransactionExpiry')->willReturn(30);

        $this->job = new AlyaPayReconcilePendingOrders(
            $this->orderHelper,
            $this->vendorTransactionService,
            $this->config
        );
    }

    public function testSkipsEntirelyWhenModuleIsInactive(): void
    {
        $this->config = $this->createMock(AlyaPayConfig::class);
        $this->config->method('isActive')->willReturn(false);
        $this->config->expects($this->never())->method('acquireReconcileLock');
        $this->orderHelper->expects($this->never())->method('getPendingAlyaPayCartIds');

        $job = new AlyaPayReconcilePendingOrders($this->orderHelper, $this->vendorTransactionService, $this->config);
        $job->execute();
    }

    public function testSkipsWhenAnotherRunHoldsTheLock(): void
    {
        $this->config = $this->createMock(AlyaPayConfig::class);
        $this->config->method('isActive')->willReturn(true);
        $this->config->method('getApiKey')->willReturn('test-key');
        $this->config->method('acquireReconcileLock')->willReturn(false);
        $this->config->expects($this->never())->method('releaseReconcileLock');
        $this->orderHelper->expects($this->never())->method('getPendingAlyaPayCartIds');

        $job = new AlyaPayReconcilePendingOrders($this->orderHelper, $this->vendorTransactionService, $this->config);
        $job->execute();
    }

    public function testReleasesTheLockEvenWhenSelectingCartsThrows(): void
    {
        $this->orderHelper->method('getPendingAlyaPayCartIds')->willThrowException(new \RuntimeException('db down'));
        $this->config->expects($this->once())->method('releaseReconcileLock');

        $this->expectException(\RuntimeException::class);
        $this->job->execute();
    }

    public function testSkipsAnOrderGroupYoungerThanTheExpiryThreshold(): void
    {
        $this->orderHelper->method('getPendingAlyaPayCartIds')->willReturn([7]);
        $this->orderHelper->method('getOrdersByCartId')->with(7)->willReturn([
            new Order(1, 7, 'ORD-1', 'alyapay', date('Y-m-d H:i:s', strtotime('-5 minutes'))),
        ]);

        $this->vendorTransactionService->expects($this->never())->method('getByVendorReference');
        $this->orderHelper->expects($this->never())->method('approveOrder');

        $this->job->execute();
    }

    public function testApprovesAllOrdersInTheCartWhenTransactionIsApproved(): void
    {
        $this->orderHelper->method('getPendingAlyaPayCartIds')->willReturn([7]);
        $this->orderHelper->method('getOrdersByCartId')->with(7)->willReturn([
            new Order(1, 7, 'ORD-1', 'alyapay', date('Y-m-d H:i:s', strtotime(self::OLD_ENOUGH))),
            new Order(2, 7, 'ORD-2', 'alyapay', date('Y-m-d H:i:s', strtotime(self::OLD_ENOUGH))),
        ]);
        $this->vendorTransactionService->method('getByVendorReference')->with('7')
            ->willReturn(['status' => 'APPROVED', 'id' => 'txn-42']);
        $this->config->method('getApprovedStatus')->willReturn(2);

        $this->orderHelper->expects($this->exactly(2))
            ->method('approveOrder')
            ->with($this->isInstanceOf(Order::class), 'txn-42', 2);

        $this->job->execute();
    }

    public function testSkipsOrdersWhosePaymentMethodChanged(): void
    {
        $this->orderHelper->method('getPendingAlyaPayCartIds')->willReturn([7]);
        $this->orderHelper->method('getOrdersByCartId')->with(7)->willReturn([
            new Order(1, 7, 'ORD-1', 'alyapay', date('Y-m-d H:i:s', strtotime(self::OLD_ENOUGH))),
            new Order(2, 7, 'ORD-2', 'bankwire', date('Y-m-d H:i:s', strtotime(self::OLD_ENOUGH))),
        ]);
        $this->vendorTransactionService->method('getByVendorReference')
            ->willReturn(['status' => 'APPROVED', 'id' => 'txn-42']);
        $this->config->method('getApprovedStatus')->willReturn(2);

        $this->orderHelper->expects($this->once())->method('approveOrder');

        $this->job->execute();
    }

    public function testClosesOrdersWhenTransactionExpired(): void
    {
        $this->orderHelper->method('getPendingAlyaPayCartIds')->willReturn([7]);
        $this->orderHelper->method('getOrdersByCartId')->with(7)->willReturn([
            new Order(1, 7, 'ORD-1', 'alyapay', date('Y-m-d H:i:s', strtotime(self::OLD_ENOUGH))),
        ]);
        $this->vendorTransactionService->method('getByVendorReference')
            ->willReturn(['status' => 'EXPIRED', 'id' => 'txn-42']);
        $this->config->method('getExpiredStatus')->willReturn(6);

        $this->orderHelper->expects($this->once())
            ->method('setOrderState')
            ->with($this->isInstanceOf(Order::class), 6, $this->stringContains('expired'));

        $this->job->execute();
    }

    public function testLeavesStillProcessingOrdersUntouched(): void
    {
        $this->orderHelper->method('getPendingAlyaPayCartIds')->willReturn([7]);
        $this->orderHelper->method('getOrdersByCartId')->with(7)->willReturn([
            new Order(1, 7, 'ORD-1', 'alyapay', date('Y-m-d H:i:s', strtotime(self::OLD_ENOUGH))),
        ]);
        $this->vendorTransactionService->method('getByVendorReference')
            ->willReturn(['status' => 'PROCESSING', 'id' => 'txn-42']);

        $this->orderHelper->expects($this->never())->method('approveOrder');
        $this->orderHelper->expects($this->never())->method('setOrderState');

        $this->job->execute();
    }

    public function testClosesOrdersWhenTransactionCanceled(): void
    {
        $this->orderHelper->method('getPendingAlyaPayCartIds')->willReturn([7]);
        $this->orderHelper->method('getOrdersByCartId')->with(7)->willReturn([
            new Order(1, 7, 'ORD-1', 'alyapay', date('Y-m-d H:i:s', strtotime(self::OLD_ENOUGH))),
        ]);
        $this->vendorTransactionService->method('getByVendorReference')
            ->willReturn(['status' => 'CANCELED', 'id' => 'txn-42']);
        $this->config->method('getCanceledStatus')->willReturn(5);
        $this->config->expects($this->never())->method('getExpiredStatus');

        $this->orderHelper->expects($this->once())
            ->method('setOrderState')
            ->with($this->isInstanceOf(Order::class), 5, $this->stringContains('canceled'));

        $this->job->execute();
    }

    public function testFallsBackToMinAgeWhenNoTransactionExpiryConfigured(): void
    {
        $this->config = $this->createMock(AlyaPayConfig::class);
        $this->config->method('isActive')->willReturn(true);
        $this->config->method('getApiKey')->willReturn('test-key');
        $this->config->method('acquireReconcileLock')->willReturn(true);
        $this->config->method('getTransactionExpiry')->willReturn(0);

        $job = new AlyaPayReconcilePendingOrders($this->orderHelper, $this->vendorTransactionService, $this->config);

        $this->orderHelper->method('getPendingAlyaPayCartIds')->willReturn([7]);

        // 15 minutes old: younger than the 20-minute MIN_AGE_MINUTES fallback — skipped.
        $this->orderHelper->method('getOrdersByCartId')->with(7)->willReturn([
            new Order(1, 7, 'ORD-1', 'alyapay', date('Y-m-d H:i:s', strtotime('-15 minutes'))),
        ]);
        $this->vendorTransactionService->expects($this->never())->method('getByVendorReference');

        $job->execute();
    }

    public function testOneOrderFailingToApproveDoesNotStopItsSiblingInTheSameCart(): void
    {
        $this->orderHelper->method('getPendingAlyaPayCartIds')->willReturn([7]);
        $this->orderHelper->method('getOrdersByCartId')->with(7)->willReturn([
            new Order(1, 7, 'ORD-1', 'alyapay', date('Y-m-d H:i:s', strtotime(self::OLD_ENOUGH))),
            new Order(2, 7, 'ORD-2', 'alyapay', date('Y-m-d H:i:s', strtotime(self::OLD_ENOUGH))),
        ]);
        $this->vendorTransactionService->method('getByVendorReference')
            ->willReturn(['status' => 'APPROVED', 'id' => 'txn-42']);
        $this->config->method('getApprovedStatus')->willReturn(2);

        $this->orderHelper->method('approveOrder')->willReturnCallback(
            function (Order $order) {
                if ($order->reference === 'ORD-1') {
                    throw new \RuntimeException('save failed');
                }
                return true;
            }
        );

        $this->orderHelper->expects($this->exactly(2))->method('approveOrder');

        $this->job->execute();
    }

    public function testOneFailingCartDoesNotStopTheOthers(): void
    {
        $this->orderHelper->method('getPendingAlyaPayCartIds')->willReturn([7, 8]);
        $this->orderHelper->method('getOrdersByCartId')->willReturnMap([
            [7, [new Order(1, 7, 'ORD-1', 'alyapay', date('Y-m-d H:i:s', strtotime(self::OLD_ENOUGH)))]],
            [8, [new Order(2, 8, 'ORD-2', 'alyapay', date('Y-m-d H:i:s', strtotime(self::OLD_ENOUGH)))]],
        ]);
        $this->vendorTransactionService->method('getByVendorReference')->willReturnCallback(
            function (string $vendorReference) {
                if ($vendorReference === '7') {
                    throw new \RuntimeException('AlyaPay API unreachable');
                }
                return ['status' => 'APPROVED', 'id' => 'txn-42'];
            }
        );
        $this->config->method('getApprovedStatus')->willReturn(2);

        $this->orderHelper->expects($this->once())->method('approveOrder');
        $this->config->expects($this->once())->method('releaseReconcileLock');

        $this->job->execute();
    }
}
