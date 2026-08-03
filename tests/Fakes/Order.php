<?php

/**
 * Test-only stand-in for PrestaShop's core `Order` class (not available
 * outside a running PrestaShop instance). Implements only the surface
 * AlyaPayReconcilePendingOrders and its collaborators actually touch —
 * same spirit as alya-magento's FakeOrderCollection.
 *
 * Named `Order` (global namespace, no `class_alias`) so it satisfies the
 * `Order` typehints on AlyaPayOrderHelper's methods. Lives under tests/ only
 * — never loaded outside the test autoloader, so it can't collide with the
 * real PrestaShop class in production.
 */
class Order
{
    public $id;
    public $id_cart;
    public $reference;
    public $module;
    public $date_add;

    public function __construct(
        int $id = 0,
        int $idCart = 0,
        string $reference = '',
        string $module = 'alyapay',
        string $dateAdd = ''
    ) {
        $this->id = $id;
        $this->id_cart = $idCart;
        $this->reference = $reference;
        $this->module = $module;
        $this->date_add = $dateAdd;
    }
}
