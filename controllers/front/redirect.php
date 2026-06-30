<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

class AlyaPayRedirectModuleFrontController extends ModuleFrontController
{
    public function postProcess()
    {
        $cart = $this->context->cart;
        if (!$cart || !$cart->id || $cart->nbProducts() === 0) {
            $this->errors[] = $this->module->l('Your cart is empty.');
            $this->redirectWithNotifications($this->context->link->getPageLink('cart'));
            return;
        }

        $customer = new Customer($cart->id_customer);
        if (!Validate::isLoadedObject($customer)) {
            $this->errors[] = $this->module->l('Customer not found.');
            $this->redirectWithNotifications($this->context->link->getPageLink('cart'));
            return;
        }

        $config = new AlyaPayConfig();
        if (!$config->isActive() || !$config->getApiKey()) {
            $this->errors[] = $this->module->l('AlyaPay is not available.');
            $this->redirectWithNotifications($this->context->link->getPageLink('cart'));
            return;
        }

        $total = (float) $cart->getOrderTotal();
        $currency = new Currency($cart->id_currency);

        try {
            $this->module->validateOrder(
                (int) $cart->id,
                (int) $config->getPendingStatus(),
                $total,
                'AlyaPay',
                null,
                [],
                (int) $currency->id,
                false,
                $customer->secure_key
            );
        } catch (\Throwable $e) {
            PrestaShopLogger::addLog(
                'AlyaPay: validateOrder failed: ' . $e->getMessage(),
                3,
                null,
                'AlyaPay'
            );
            $this->errors[] = $this->module->l('Unable to create order. Please try again.');
            $this->redirectWithNotifications($this->context->link->getPageLink('cart'));
            return;
        }

        $orderId = (int) $this->module->currentOrder;
        $order = new Order($orderId);
        if (!Validate::isLoadedObject($order)) {
            $this->errors[] = $this->module->l('Order creation failed.');
            $this->redirectWithNotifications($this->context->link->getPageLink('cart'));
            return;
        }

        $this->context->cookie->alyapay_order_id = $orderId;
        $this->context->cookie->write();

        try {
            $client = new AlyaPayApiClient($config);
            $sessionService = new AlyaPaySessionIntentService($client);
            $response = $sessionService->createSessionIntent($cart);

            $checkoutUrl = $response['checkout_url'] ?? null;
            $checkoutToken = $response['checkout_token'] ?? null;
            $paymentIntentId = $response['payment_intent_id'] ?? null;

            if (!$checkoutUrl || !$checkoutToken) {
                throw new \Exception('Invalid session-intent response');
            }

            $successUrl = $this->context->link->getModuleLink('alyapay', 'success', [], true);
            $checkoutUrl .= (strpos($checkoutUrl, '?') !== false ? '&' : '?')
                . 'redirect_url=' . rawurlencode($successUrl);

            $this->storePaymentMeta($order, $checkoutToken, $paymentIntentId);

            Tools::redirect($checkoutUrl);
        } catch (\Throwable $e) {
            $handler = new AlyaPayErrorHandler(new AlyaPayMessageMap());
            $result = $handler->handle($e, AlyaPayErrorContext::SESSION_INTENT);

            $orderHelper = new AlyaPayOrderHelper();
            $orderHelper->cancelOrder($order, 'AlyaPay redirect failed: ' . $e->getMessage());
            $orderHelper->restoreCart((int) $cart->id);

            $this->errors[] = $result->getUserMessage();
            $this->redirectWithNotifications($this->context->link->getPageLink('cart'));
        }
    }

    private function storePaymentMeta(Order $order, string $checkoutToken, ?string $paymentIntentId): void
    {
        $payments = OrderPayment::getByOrderReference($order->reference);
        if (!empty($payments)) {
            $payment = end($payments);
            $payment->transaction_id = $paymentIntentId ?: $checkoutToken;
            $payment->save();
        }
    }
}
