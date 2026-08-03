<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * AlyaPay Transaction Lookup by Vendor Reference
 * GET /api/v1/public/partner/transactions/vendor/{vendorReference}
 *
 * The vendor reference is the cart ID — one AlyaPay transaction covers a
 * whole cart, which may split into several orders (multivendor setups).
 */
class AlyaPayVendorTransactionService
{
    private const API_PATH = '/api/v1/public/partner/transactions/vendor/%s';

    /** @var AlyaPayApiClient */
    private $client;

    public function __construct(AlyaPayApiClient $client)
    {
        $this->client = $client;
    }

    /**
     * @return array{status: string, id: string, vendorReference: string, ...}
     */
    public function getByVendorReference(string $vendorReference): array
    {
        $path = sprintf(self::API_PATH, rawurlencode($vendorReference));
        return $this->client->getWithApiKey($path);
    }
}
