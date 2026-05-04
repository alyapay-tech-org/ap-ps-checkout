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
     * @param Order $order
     * @return array{payment_intent_id: string, checkout_token: string, checkout_url: string, expires_in: int}
     */
    public function createSessionIntent(Order $order): array
    {
        $currency = new Currency($order->id_currency);

        $payload = [
            'currency' => $currency->iso_code ?: 'MAD',
            'total' => (float) $order->total_paid,
            'items' => $this->buildItems($order, $currency),
            'vendorReference' => $order->reference,
        ];

        return $this->client->postWithApiKey(self::API_PATH, $payload);
    }

    private function buildItems(Order $order, Currency $currency): array
    {
        $items = [];
        $products = $order->getProducts();

        foreach ($products as $product) {
            $unitPrice = (float) ($product['unit_price_tax_incl'] ?? $product['product_price'] ?? 0);
            if ($unitPrice <= 0) {
                continue;
            }
            $items[] = [
                'id' => substr((string) ($product['product_reference'] ?: $product['product_id']), 0, 64),
                'name' => substr((string) $product['product_name'], 0, 255),
                'price' => $unitPrice,
                'quantity' => (int) max(1, $product['product_quantity']),
            ];
        }

        $shippingTotal = (float) $order->total_shipping_tax_incl;
        if ($shippingTotal > 0) {
            $carrier = new Carrier($order->id_carrier);
            $items[] = [
                'id' => 'shipping',
                'name' => substr((string) ($carrier->name ?: 'Shipping'), 0, 255),
                'price' => $shippingTotal,
                'quantity' => 1,
            ];
        }

        if (empty($items)) {
            $items[] = [
                'id' => 'order_' . $order->id,
                'name' => 'Order #' . $order->reference,
                'price' => (float) $order->total_paid,
                'quantity' => 1,
            ];
        }

        return $items;
    }
}
