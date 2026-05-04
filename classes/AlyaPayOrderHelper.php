<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

class AlyaPayOrderHelper
{
    public function getOrderByReference(string $reference): ?Order
    {
        $sql = new DbQuery();
        $sql->select('id_order');
        $sql->from('orders');
        $sql->where('reference = \'' . pSQL($reference) . '\'');
        $sql->orderBy('id_order DESC');

        $idOrder = (int) Db::getInstance()->getValue($sql);
        if ($idOrder <= 0) {
            return null;
        }

        $order = new Order($idOrder);
        return Validate::isLoadedObject($order) ? $order : null;
    }

    public function getOrderById(int $idOrder): ?Order
    {
        $order = new Order($idOrder);
        return Validate::isLoadedObject($order) ? $order : null;
    }

    public function approveOrder(Order $order, string $transactionId, int $targetStateId): bool
    {
        if (!Validate::isLoadedObject($order)) {
            return false;
        }

        $currentState = (int) $order->getCurrentState();
        if ($currentState === (int) Configuration::get('PS_OS_CANCELED')) {
            return false;
        }

        $this->addTransactionId($order, $transactionId);
        $this->setOrderState(
            $order,
            $targetStateId,
            sprintf('AlyaPay: Payment approved. Transaction ID: %s', $transactionId)
        );

        return true;
    }

    public function cancelOrder(Order $order, string $comment = ''): bool
    {
        if (!Validate::isLoadedObject($order)) {
            return false;
        }

        $currentState = (int) $order->getCurrentState();
        if ($currentState === (int) Configuration::get('PS_OS_CANCELED')) {
            return true;
        }

        $canceledState = (int) Configuration::get('PS_OS_CANCELED');
        return $this->setOrderState($order, $canceledState, $comment ?: 'AlyaPay: Order cancelled');
    }

    public function setOrderState(Order $order, int $stateId, string $comment = ''): bool
    {
        if (!Validate::isLoadedObject($order)) {
            return false;
        }

        if ((int) $order->getCurrentState() === $stateId) {
            return true;
        }

        $history = new OrderHistory();
        $history->id_order = (int) $order->id;
        $history->changeIdOrderState($stateId, $order, true);
        $history->addWithemail(true);

        if (!empty($comment)) {
            $msg = new Message();
            $msg->message = $comment;
            $msg->id_order = (int) $order->id;
            $msg->private = 1;
            $msg->add();
        }

        return true;
    }

    public function addTransactionId(Order $order, string $transactionId): void
    {
        if (empty($transactionId) || !Validate::isLoadedObject($order)) {
            return;
        }

        $payments = OrderPayment::getByOrderReference($order->reference);
        if (!empty($payments)) {
            $payment = end($payments);
            $payment->transaction_id = $transactionId;
            $payment->save();
            return;
        }

        $payment = new OrderPayment();
        $payment->order_reference = $order->reference;
        $payment->id_currency = $order->id_currency;
        $payment->amount = $order->total_paid;
        $payment->payment_method = 'AlyaPay';
        $payment->transaction_id = $transactionId;
        $payment->add();
    }

    public function restoreCart(int $idCart): ?Cart
    {
        $oldCart = new Cart($idCart);
        if (!Validate::isLoadedObject($oldCart)) {
            return null;
        }

        $newCart = $oldCart->duplicate();
        if (!$newCart || !isset($newCart['cart'])) {
            return null;
        }

        $cart = $newCart['cart'];
        $context = Context::getContext();
        $context->cart = $cart;
        $context->cookie->id_cart = (int) $cart->id;
        $context->cookie->write();

        return $cart;
    }
}
