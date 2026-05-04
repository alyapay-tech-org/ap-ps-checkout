<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

class AlyaPayScheduleService
{
    private const API_PATH = '/api/v1/public/transactions/%s/schedules';

    /** @var AlyaPayApiClient */
    private $client;

    public function __construct(AlyaPayApiClient $client)
    {
        $this->client = $client;
    }

    public function getSchedules(string $transactionId): array
    {
        $path = sprintf(self::API_PATH, $transactionId);
        return $this->client->getWithApiKey($path);
    }
}
