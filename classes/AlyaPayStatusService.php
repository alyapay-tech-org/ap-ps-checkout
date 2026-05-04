<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

class AlyaPayStatusService
{
    private const API_PATH = '/api/v1/public/transactions/%s/status';

    /** @var AlyaPayApiClient */
    private $client;

    public function __construct(AlyaPayApiClient $client)
    {
        $this->client = $client;
    }

    public function getStatus(string $transactionId): array
    {
        $path = sprintf(self::API_PATH, $transactionId);
        return $this->client->getWithApiKey($path);
    }
}
