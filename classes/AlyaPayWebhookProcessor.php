<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

class AlyaPayWebhookProcessor
{
    private const EVENT_APPROVED = 'transaction.approved';
    private const EVENT_CANCELLED = 'transaction.cancelled';
    private const EVENT_EXPIRED = 'transaction.expired';

    /** @var AlyaPayOrderHelper */
    private $orderHelper;

    /** @var AlyaPayConfig */
    private $config;

    public function __construct(AlyaPayOrderHelper $orderHelper, AlyaPayConfig $config)
    {
        $this->orderHelper = $orderHelper;
        $this->config = $config;
    }

    public function process(string $payload): bool
    {
        try {
            $data = json_decode($payload, true);
            if (!is_array($data)) {
                PrestaShopLogger::addLog('AlyaPay webhook: invalid payload structure', 3, null, 'AlyaPay');
                return false;
            }

            $event = $data['event'] ?? '';
            $webhookData = $data['data'] ?? [];

            if (!is_array($webhookData)) {
                PrestaShopLogger::addLog('AlyaPay webhook: missing data', 3, null, 'AlyaPay');
                return false;
            }

            $knownEvents = [self::EVENT_APPROVED, self::EVENT_CANCELLED, self::EVENT_EXPIRED];
            if (!in_array($event, $knownEvents)) {
                PrestaShopLogger::addLog('AlyaPay webhook: ignoring event ' . $event, 1, null, 'AlyaPay');
                return true;
            }

            $cartId = (int) ($webhookData['vendorReference'] ?? 0);
            if ($cartId <= 0) {
                PrestaShopLogger::addLog(
                    'AlyaPay webhook: missing or invalid vendorReference (cart ID) in payload',
                    2,
                    null,
                    'AlyaPay'
                );
                return true;
            }

            $orders = $this->orderHelper->getOrdersByCartId($cartId);
            if (empty($orders)) {
                PrestaShopLogger::addLog(
                    'AlyaPay webhook: no orders found for cart ' . $cartId,
                    2,
                    null,
                    'AlyaPay'
                );
                return true;
            }

            $transactionId = (string) ($webhookData['id'] ?? '');

            if ($event === self::EVENT_APPROVED) {
                $targetStatus = $this->config->getApprovedStatus();

                foreach ($orders as $order) {
                    if ($order->getCurrentState() === (int) Configuration::get('PS_OS_CANCELED')) {
                        continue;
                    }
                    // Skip if already processed (idempotency)
                    $payments = OrderPayment::getByOrderReference($order->reference);
                    $alreadyProcessed = false;
                    foreach ($payments as $p) {
                        if (!empty($p->transaction_id) && $p->transaction_id === $transactionId) {
                            $alreadyProcessed = true;
                            break;
                        }
                    }
                    if ($alreadyProcessed) {
                        continue;
                    }

                    try {
                        $this->orderHelper->approveOrder($order, $transactionId, $targetStatus);
                        PrestaShopLogger::addLog(
                            sprintf('AlyaPay webhook: approved order %s (cart %d)', $order->reference, $cartId),
                            1,
                            null,
                            'AlyaPay'
                        );
                    } catch (\Throwable $e) {
                        PrestaShopLogger::addLog(
                            sprintf('AlyaPay webhook: error approving order %s: %s', $order->reference, $e->getMessage()),
                            3,
                            null,
                            'AlyaPay'
                        );
                    }
                }
            } else {
                $comment = $event === self::EVENT_EXPIRED
                    ? sprintf('AlyaPay: Transaction expired (webhook). Transaction ID: %s', $transactionId)
                    : sprintf('AlyaPay: Payment cancelled (webhook). Transaction ID: %s', $transactionId);

                if ($event === self::EVENT_EXPIRED) {
                    $targetStatus = $this->config->getExpiredStatus();
                } else {
                    $targetStatus = $this->config->getCanceledStatus();
                }

                foreach ($orders as $order) {
                    if ($order->getCurrentState() === (int) Configuration::get('PS_OS_CANCELED')) {
                        continue;
                    }
                    // Customer switched payment method — webhook belongs to abandoned AlyaPay attempt.
                    if ($order->module !== 'alyapay') {
                        PrestaShopLogger::addLog(
                            sprintf(
                                'AlyaPay webhook: ignoring %s for order %s — payment method changed to %s',
                                $event,
                                $order->reference,
                                $order->module
                            ),
                            1,
                            null,
                            'AlyaPay'
                        );
                        continue;
                    }

                    try {
                        $this->orderHelper->setOrderState($order, $targetStatus, $comment);
                        PrestaShopLogger::addLog(
                            sprintf('AlyaPay webhook: applied %s to order %s (cart %d)', $event, $order->reference, $cartId),
                            1,
                            null,
                            'AlyaPay'
                        );
                    } catch (\Throwable $e) {
                        PrestaShopLogger::addLog(
                            sprintf('AlyaPay webhook: error applying %s to order %s: %s', $event, $order->reference, $e->getMessage()),
                            3,
                            null,
                            'AlyaPay'
                        );
                    }
                }
            }

            return true;
        } catch (\Throwable $e) {
            PrestaShopLogger::addLog(
                'AlyaPay webhook error: ' . $e->getMessage(),
                3,
                null,
                'AlyaPay'
            );
            return false;
        }
    }

}
