<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Reconciles AlyaPay orders stuck pending — covers the case where neither
 * the webhook (blocked by merchant firewall/WAF) nor the browser redirect
 * (customer closed the tab) ever confirmed the transaction outcome. Polls
 * AlyaPay directly by vendor reference (the cart ID), which needs no
 * transaction_id captured earlier in the flow.
 *
 * Mirrors alya-magento's Cron\ReconcilePendingOrders — see
 * docs/platform-parity.md at the repo root for why this exists here too.
 */
class AlyaPayReconcilePendingOrders
{
    private const APPROVED_STATUSES = ['APPROVED', 'COMPLETED'];
    private const FAILED_STATUSES = ['CANCELED', 'EXPIRED', 'DECLINED', 'FAILED'];

    /** SQL prefilter floor — narrows the scanned set before per-cart expiry is checked */
    private const MIN_AGE_MINUTES = 20;

    /** Wait this long past the configured transaction expiry before polling */
    private const GRACE_MINUTES = 5;

    /** @var AlyaPayOrderHelper */
    private $orderHelper;

    /** @var AlyaPayVendorTransactionService */
    private $vendorTransactionService;

    /** @var AlyaPayConfig */
    private $config;

    public function __construct(
        AlyaPayOrderHelper $orderHelper,
        AlyaPayVendorTransactionService $vendorTransactionService,
        AlyaPayConfig $config
    ) {
        $this->orderHelper = $orderHelper;
        $this->vendorTransactionService = $vendorTransactionService;
        $this->config = $config;
    }

    /**
     * Cron entry point.
     */
    public function execute(): void
    {
        if (!$this->config->isActive() || !$this->config->getApiKey()) {
            return;
        }

        // Overlap guard — PrestaShop's cron mechanism, unlike Magento's, gives no
        // built-in single-flight guarantee for a job, so it's made explicit here.
        if (!$this->config->acquireReconcileLock()) {
            return;
        }

        try {
            $cartIds = $this->orderHelper->getPendingAlyaPayCartIds(self::MIN_AGE_MINUTES);
            foreach ($cartIds as $cartId) {
                try {
                    $this->reconcileCart($cartId);
                } catch (Throwable $e) {
                    $this->logError(sprintf(
                        'AlyaPay cron: unexpected error reconciling cart %d: %s',
                        $cartId,
                        $e->getMessage()
                    ));
                }
            }
        } finally {
            $this->config->releaseReconcileLock();
        }
    }

    private function reconcileCart(int $cartId): void
    {
        $orders = $this->orderHelper->getOrdersByCartId($cartId);
        if (empty($orders)) {
            return;
        }

        $anchor = $this->earliestCreationTime($orders);
        $expiryMinutes = $this->config->getTransactionExpiry();
        $thresholdMinutes = $expiryMinutes > 0 ? $expiryMinutes + self::GRACE_MINUTES : self::MIN_AGE_MINUTES;
        if ($anchor === false || (time() - $anchor) < ($thresholdMinutes * 60)) {
            return;
        }

        try {
            $response = $this->vendorTransactionService->getByVendorReference((string) $cartId);
        } catch (Throwable $e) {
            $this->logError(sprintf('AlyaPay cron: vendor lookup failed for cart %d: %s', $cartId, $e->getMessage()));
            return;
        }

        $status = strtoupper((string) ($response['status'] ?? ''));
        $transactionId = (string) ($response['id'] ?? '');

        if ($status === '') {
            return;
        }

        if (in_array($status, self::APPROVED_STATUSES, true)) {
            $targetStatus = $this->config->getApprovedStatus();
            $this->closeOrders($orders, function ($order) use ($transactionId, $targetStatus): void {
                $this->orderHelper->approveOrder($order, $transactionId, $targetStatus);
            }, 'approve');
            return;
        }

        if (in_array($status, self::FAILED_STATUSES, true)) {
            $targetStatus = $status === 'EXPIRED'
                ? $this->config->getExpiredStatus()
                : $this->config->getCanceledStatus();
            $comment = sprintf(
                'AlyaPay: Transaction %s (cron reconciliation). Transaction ID: %s',
                strtolower($status),
                $transactionId
            );
            $this->closeOrders($orders, function ($order) use ($targetStatus, $comment): void {
                $this->orderHelper->setOrderState($order, $targetStatus, $comment);
                AlyaPayEmailQueue::discard($order->reference);
            }, 'close');
            return;
        }

        // PENDING/PROCESSING — transaction still in progress on AlyaPay's side, leave orders as-is.
    }

    /**
     * Applies $action to every order still on the alyapay payment method,
     * isolating each order's failure so one bad order in a cart doesn't
     * block its siblings (multivendor split-order setups).
     */
    private function closeOrders(array $orders, callable $action, string $verb): void
    {
        foreach ($orders as $order) {
            if ($order->module !== 'alyapay') {
                continue;
            }
            try {
                $action($order);
            } catch (Throwable $e) {
                $this->logError(sprintf(
                    'AlyaPay cron: %s failed for order %s: %s',
                    $verb,
                    $order->reference,
                    $e->getMessage()
                ));
            }
        }
    }

    /**
     * @param array $orders
     * @return int|false
     */
    private function earliestCreationTime(array $orders)
    {
        $earliest = null;
        foreach ($orders as $order) {
            $timestamp = strtotime((string) $order->date_add);
            if ($timestamp !== false && ($earliest === null || $timestamp < $earliest)) {
                $earliest = $timestamp;
            }
        }
        return $earliest ?? false;
    }

    private function logError(string $message): void
    {
        try {
            PrestaShopLogger::addLog($message, 3, null, 'AlyaPay');
        } catch (Throwable $e) {
            // Logging must never break reconciliation.
        }
    }
}
