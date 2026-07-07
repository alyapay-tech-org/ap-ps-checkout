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

        // Check if split table exists once for both lookup directions.
        $splitTableExists = (bool) Db::getInstance()->getValue(
            "SELECT COUNT(*) FROM information_schema.tables
             WHERE table_schema = DATABASE()
             AND table_name = '" . pSQL(_DB_PREFIX_ . 'presta_split_order') . "'"
        );

        if (!empty($orders)) {
            // cartId may be a sub-cart — check if it has a parent and re-run from parent
            // so all sibling sub-orders are included.
            if ($splitTableExists) {
                $parentCartId = (int) Db::getInstance()->getValue(
                    'SELECT id_original_cart FROM `' . _DB_PREFIX_ . 'presta_split_order`
                     WHERE id_cart = ' . (int) $cartId
                );
                if ($parentCartId > 0 && $parentCartId !== $cartId) {
                    return $this->getOrdersByCartId($parentCartId);
                }
            }
            return $orders;
        }

        // cartId is original cart — find all sub-carts and collect their orders.
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
            foreach ($this->loadOrdersByCartId($subCartId) as $order) {
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

        // Release the order_conf email that was held back while payment was pending.
        AlyaPayEmailQueue::flush($order);

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
        $result = $this->setOrderState($order, $canceledState, $comment ?: 'AlyaPay: Order cancelled');

        // Payment failed — the held order_conf email must never be sent.
        AlyaPayEmailQueue::discard($order->reference);

        return $result;
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

        // Must go through Order::addOrderPayment() (not a raw OrderPayment::add())
        // so orders.total_paid_real is updated — the BO customer list "Sales"
        // column sums total_paid_real of valid orders and would stay at 0 otherwise.
        $order->addOrderPayment($order->total_paid, 'AlyaPay', $transactionId);
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
