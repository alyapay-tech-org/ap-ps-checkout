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

    /**
     * Returns all orders for a given cart ID — covers multivendor split-order setups.
     *
     * @return Order[]
     */
    public function getOrdersByCartId(int $cartId): array
    {
        if ($cartId <= 0) {
            return [];
        }

        $orders = $this->loadOrdersByCartId($cartId);
        if (!empty($orders)) {
            return $orders;
        }

        // Fallback: multivendor split module stores sub-carts in ps_presta_split_order.
        // id_original_cart = original session cart; each row has its own id_cart (sub-cart).
        $splitTableExists = (bool) Db::getInstance()->getValue(
            "SELECT COUNT(*) FROM information_schema.tables
             WHERE table_schema = DATABASE()
             AND table_name = '" . pSQL(_DB_PREFIX_ . 'presta_split_order') . "'"
        );
        if (!$splitTableExists) {
            return [];
        }

        $subCartRows = Db::getInstance()->executeS(
            'SELECT id_cart FROM `' . _DB_PREFIX_ . 'presta_split_order`
             WHERE id_original_cart = ' . (int) $cartId
        );
        if (!$subCartRows) {
            return [];
        }

        foreach ($subCartRows as $row) {
            $subCartId = (int) $row['id_cart'];
            if ($subCartId <= 0) {
                continue;
            }
            $subOrders = $this->loadOrdersByCartId($subCartId);
            foreach ($subOrders as $order) {
                $orders[] = $order;
            }
        }

        return $orders;
    }

    private function loadOrdersByCartId(int $cartId): array
    {
        $sql = new DbQuery();
        $sql->select('id_order');
        $sql->from('orders');
        $sql->where('id_cart = ' . (int) $cartId);
        $sql->orderBy('id_order ASC');

        $rows = Db::getInstance()->executeS($sql);
        if (!$rows) {
            return [];
        }

        $orders = [];
        foreach ($rows as $row) {
            $order = new Order((int) $row['id_order']);
            if (Validate::isLoadedObject($order)) {
                $orders[] = $order;
            }
        }

        return $orders;
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

        // Webhook runs without frontend context — addWithemail needs currency/locale precision.
        // Inject order currency into context so ComputingPrecision resolves correctly.
        $context = Context::getContext();
        if (!$context->currency || !Validate::isLoadedObject($context->currency)) {
            $context->currency = new Currency((int) $order->id_currency);
        }
        if (!$context->language || !Validate::isLoadedObject($context->language)) {
            $context->language = new Language((int) Configuration::get('PS_LANG_DEFAULT'));
        }

        $history = new OrderHistory();
        $history->id_order = (int) $order->id;
        $history->changeIdOrderState($stateId, $order, true);
        try {
            $history->addWithemail(true);
        } catch (\Throwable $e) {
            // Final fallback — skips email but persists state and invoice.
            $history->add();
        }

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
