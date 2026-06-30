<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

class AlyaPaySessionIntentService
{
    private const API_PATH = '/api/v1/public/session-intents';

    /** @var AlyaPayApiClient */
    private $client;

    public function __construct(AlyaPayApiClient $client)
    {
        $this->client = $client;
    }

    /**
     * @param Cart $cart  Source of truth: items, total, currency, and vendorReference (cart ID).
     * @return array{payment_intent_id: string, checkout_token: string, checkout_url: string, expires_in: int}
     */
    public function createSessionIntent(Cart $cart): array
    {
        $currency = new Currency($cart->id_currency);
        $total    = (float) $cart->getOrderTotal(true, Cart::BOTH);

        $payload = [
            'currency'        => $currency->iso_code ?: 'MAD',
            'total'           => $total,
            'items'           => $this->buildItems($cart, $currency, $total),
            'vendorReference' => (string) $cart->id,
        ];

        return $this->client->postWithApiKey(self::API_PATH, $payload);
    }

    private function buildItems(Cart $cart, Currency $currency, float $cartTotal): array
    {
        $items = [];

        foreach ($cart->getProducts() as $product) {
            $unitPrice = (float) ($product['price_wt'] ?? $product['price'] ?? 0);
            if ($unitPrice <= 0) {
                continue;
            }
            $items[] = [
                'id'       => substr((string) ($product['reference'] ?: $product['id_product']), 0, 64),
                'name'     => substr((string) $product['name'], 0, 255),
                'price'    => $unitPrice,
                'quantity' => (int) max(1, $product['cart_quantity']),
            ];
        }

        $shippingTotal = (float) $cart->getOrderTotal(true, Cart::ONLY_SHIPPING);
        if ($shippingTotal > 0) {
            $carrier = new Carrier($cart->id_carrier);
            $items[] = [
                'id'       => 'shipping',
                'name'     => substr((string) ($carrier->name ?: 'Shipping'), 0, 255),
                'price'    => $shippingTotal,
                'quantity' => 1,
            ];
        }

        if (empty($items)) {
            $items[] = [
                'id'       => 'cart_' . $cart->id,
                'name'     => 'Cart #' . $cart->id,
                'price'    => $cartTotal,
                'quantity' => 1,
            ];
        }

        return $items;
    }
}
