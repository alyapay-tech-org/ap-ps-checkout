<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

class AlyaPayCancelModuleFrontController extends ModuleFrontController
{
    public function postProcess()
    {
        $orderId = (int) ($this->context->cookie->alyapay_order_id ?? 0);
        if (!$orderId) {
            $orderId = (int) $this->module->currentOrder;
        }

        $orderHelper = new AlyaPayOrderHelper();

        if ($orderId > 0) {
            $order = $orderHelper->getOrderById($orderId);
            if ($order) {
                $orderHelper->cancelOrder($order, 'Customer cancelled payment');
                $orderHelper->restoreCart((int) $order->id_cart);
            }
        } elseif ($this->context->cart && $this->context->cart->id) {
            $orderHelper->restoreCart((int) $this->context->cart->id);
        }

        $this->warning[] = $this->module->l('Payment was cancelled. You can choose another payment method.');
        $this->redirectWithNotifications($this->context->link->getPageLink('order'));
    }
}
