<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

class AlyaPayPartnerConfigService
{
    private const API_PATH = '/api/v1/public/partner/config';

    /** @var AlyaPayApiClient */
    private $client;

    /** @var AlyaPayConfig */
    private $config;

    public function __construct(AlyaPayApiClient $client, AlyaPayConfig $config)
    {
        $this->client = $client;
        $this->config = $config;
    }

    public function updateConfig(array $data): array
    {
        return $this->client->putWithApiKey(self::API_PATH, $data);
    }
}
