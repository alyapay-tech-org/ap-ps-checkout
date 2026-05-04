<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

class AlyaPaySuccessModuleFrontController extends ModuleFrontController
{
    private const APPROVED_STATUSES = ['APPROVED', 'COMPLETED'];
    private const FAILED_STATUSES = ['CANCELED', 'EXPIRED', 'DECLINED', 'FAILED'];

    public function postProcess()
    {
        $urlStatus = strtoupper((string) Tools::getValue('status', ''));
        $transactionId = Tools::getValue('transaction_id', '') ?: Tools::getValue('transactionId', '');

        $orderId = (int) ($this->context->cookie->alyapay_order_id ?? 0);
        if (!$orderId) {
            $orderId = (int) $this->module->currentOrder;
        }

        if ($orderId) {
            $this->context->cookie->alyapay_order_id = null;
            $this->context->cookie->write();
        }

        if (!$orderId) {
            $this->errors[] = $this->module->l('Invalid return from payment.');
            $this->redirectWithNotifications($this->context->link->getPageLink('cart'));
            return;
        }

        $orderHelper = new AlyaPayOrderHelper();
        $order = $orderHelper->getOrderById($orderId);

        if (!$order) {
            $this->errors[] = $this->module->l('Order not found.');
            $this->redirectWithNotifications($this->context->link->getPageLink('cart'));
            return;
        }

        $config = new AlyaPayConfig();
        $currentState = (int) $order->getCurrentState();

        // 1. Webhook already approved the order -- just redirect to confirmation
        if ($currentState === $config->getApprovedStatus()) {
            $this->redirectToConfirmation($order);
            return;
        }

        // 2. Webhook already cancelled/expired the order
        if (in_array($currentState, [$config->getCanceledStatus(), $config->getExpiredStatus(), (int) Configuration::get('PS_OS_CANCELED')])) {
            $this->errors[] = $this->module->l('Payment was not completed. Please try again or choose another payment method.');
            $orderHelper->restoreCart((int) $order->id_cart);
            $this->redirectWithNotifications($this->context->link->getPageLink('order'));
            return;
        }

        // 3. URL says FAILURE -- cancel and redirect back
        if ($urlStatus === 'FAILURE') {
            $this->errors[] = $this->module->l('Payment failed. Please try again or choose another payment method.');
            $orderHelper->cancelOrder($order, 'Payment failed (URL status: FAILURE)');
            $orderHelper->restoreCart((int) $order->id_cart);
            $this->redirectWithNotifications($this->context->link->getPageLink('order'));
            return;
        }

        // 4. URL says CANCELED/EXPIRED -- restore cart, let webhook handle final status
        if ($urlStatus === 'CANCELED' || $urlStatus === 'EXPIRED') {
            $this->warning[] = $this->module->l('Payment was cancelled. You can try again.');
            $orderHelper->restoreCart((int) $order->id_cart);
            $this->redirectWithNotifications($this->context->link->getPageLink('order'));
            return;
        }

        // 5. Order still pending -- webhook hasn't arrived yet.
        //    Fall back to API status check.
        if ($transactionId) {
            try {
                $client = new AlyaPayApiClient($config);
                $statusService = new AlyaPayStatusService($client);
                $statusResponse = $statusService->getStatus($transactionId);
                $apiStatus = strtoupper($statusResponse['status'] ?? '');

                if (in_array($apiStatus, self::APPROVED_STATUSES)) {
                    $targetStatus = $config->getApprovedStatus();
                    $orderHelper->approveOrder($order, $transactionId, $targetStatus);
                    $this->redirectToConfirmation($order);
                    return;
                }

                if (in_array($apiStatus, self::FAILED_STATUSES)) {
                    $this->errors[] = sprintf(
                        $this->module->l('Payment was not successful (status: %s).'),
                        $apiStatus
                    );
                    $orderHelper->cancelOrder($order, 'Payment failed (API fallback): ' . $apiStatus);
                    $orderHelper->restoreCart((int) $order->id_cart);
                    $this->redirectWithNotifications($this->context->link->getPageLink('order'));
                    return;
                }

                // Still processing (PENDING etc.) -- show confirmation, webhook will finalize
                $this->redirectToConfirmation($order);
                return;
            } catch (\Throwable $e) {
                // API fallback failed -- don't cancel, leave order pending for webhook
                PrestaShopLogger::addLog(
                    'AlyaPay: API status fallback failed, relying on webhook: ' . $e->getMessage(),
                    2,
                    null,
                    'AlyaPay'
                );
                $this->redirectToConfirmation($order);
                return;
            }
        }

        // No transaction ID and no webhook yet -- redirect to confirmation and wait for webhook
        $this->redirectToConfirmation($order);
    }

    private function redirectToConfirmation(Order $order): void
    {
        $params = [
            'id_cart' => (int) $order->id_cart,
            'id_module' => (int) $this->module->id,
            'id_order' => (int) $order->id,
            'key' => $order->secure_key,
        ];

        Tools::redirect($this->context->link->getPageLink(
            'order-confirmation',
            true,
            null,
            $params
        ));
    }
}
