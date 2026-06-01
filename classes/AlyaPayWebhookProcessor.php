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

            $order = $this->resolveOrder($webhookData);
            if (!$order) {
                PrestaShopLogger::addLog(
                    'AlyaPay webhook: order not found for vendor ref ' . ($webhookData['vendorReference'] ?? 'null'),
                    2,
                    null,
                    'AlyaPay'
                );
                return true;
            }

            $transactionId = (string) ($webhookData['id'] ?? '');

            if ($event === self::EVENT_APPROVED) {
                $currentState = (int) $order->getCurrentState();
                if ($currentState === (int) Configuration::get('PS_OS_CANCELED')) {
                    return true;
                }
                $payments = OrderPayment::getByOrderReference($order->reference);
                if (!empty($payments)) {
                    foreach ($payments as $p) {
                        if (!empty($p->transaction_id) && $p->transaction_id === $transactionId) {
                            return true;
                        }
                    }
                }

                $targetStatus = $this->config->getApprovedStatus();
                $this->orderHelper->approveOrder($order, $transactionId, $targetStatus);
            } else {
                $currentState = (int) $order->getCurrentState();
                if ($currentState === (int) Configuration::get('PS_OS_CANCELED')) {
                    return true;
                }

                // Customer switched payment method — webhook belongs to abandoned AlyaPay attempt, ignore it.
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
                    return true;
                }

                if ($event === self::EVENT_EXPIRED) {
                    $targetStatus = $this->config->getExpiredStatus();
                } else {
                    $targetStatus = $this->config->getCanceledStatus();
                }

                $comment = $event === self::EVENT_EXPIRED
                    ? sprintf('AlyaPay: Transaction expired (webhook). Transaction ID: %s', $transactionId)
                    : sprintf('AlyaPay: Payment cancelled (webhook). Transaction ID: %s', $transactionId);

                $this->orderHelper->setOrderState($order, $targetStatus, $comment);
            }

            PrestaShopLogger::addLog(
                sprintf('AlyaPay webhook: order %s updated (event: %s)', $order->reference, $event),
                1,
                null,
                'AlyaPay'
            );

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

    private function resolveOrder(array $data): ?Order
    {
        $vendorRef = $data['vendorReference'] ?? $data['orderReference'] ?? null;
        if ($vendorRef !== null && $vendorRef !== '') {
            return $this->orderHelper->getOrderByReference((string) $vendorRef);
        }
        return null;
    }
}
