<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

class AlyaPayApiClient
{
    private const CONTENT_TYPE = 'application/json';

    /** @var AlyaPayConfig */
    private $config;

    public function __construct(AlyaPayConfig $config)
    {
        $this->config = $config;
    }

    public function postWithApiKey(string $path, array $data): array
    {
        $apiKey = $this->config->getApiKey();
        if (!$apiKey) {
            throw new \Exception('AlyaPay API key is not configured.');
        }
        return $this->request('POST', $path, $data, ['X-Api-Key: ' . $apiKey]);
    }

    public function getWithApiKey(string $path): array
    {
        $apiKey = $this->config->getApiKey();
        if (!$apiKey) {
            throw new \Exception('AlyaPay API key is not configured.');
        }
        return $this->request('GET', $path, [], ['X-Api-Key: ' . $apiKey]);
    }

    public function putWithApiKey(string $path, array $data): array
    {
        $apiKey = $this->config->getApiKey();
        if (!$apiKey) {
            throw new \Exception('AlyaPay API key is not configured.');
        }
        return $this->request('PUT', $path, $data, ['X-Api-Key: ' . $apiKey]);
    }

    public function get(string $path, array $extraHeaders = []): array
    {
        return $this->request('GET', $path, [], $extraHeaders);
    }

    private function request(string $method, string $path, array $data = [], array $extraHeaders = []): array
    {
        $url = $this->config->getApiBaseUrl() . $path;
        $headers = ['Content-Type: ' . self::CONTENT_TYPE];
        foreach ($extraHeaders as $header) {
            $headers[] = $header;
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        } elseif ($method === 'PUT') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        $response = curl_exec($ch);
        $statusCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            throw new AlyaPayApiException('cURL error: ' . $curlError, 0);
        }

        if ($this->config->isDebugEnabled()) {
            PrestaShopLogger::addLog(
                sprintf('AlyaPay API %s %s -> %d', $method, $path, $statusCode),
                1,
                null,
                'AlyaPay'
            );
        }

        if ($statusCode >= 400) {
            throw $this->parseErrorResponse((string) $response, $statusCode, $path);
        }

        $decoded = json_decode((string) $response, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function parseErrorResponse(string $body, int $statusCode, string $path): AlyaPayApiException
    {
        $key = null;
        $code = null;
        $parameters = [];
        $validationErrors = [];
        $message = trim($body);

        if (!empty($body)) {
            $data = json_decode($body, true);
            if (is_array($data)) {
                if (isset($data['key'], $data['code'])) {
                    $key = (string) $data['key'];
                    $code = (int) $data['code'];
                    $parameters = is_array($data['parameters'] ?? null) ? $data['parameters'] : [];
                } elseif (isset($data[0]) && is_array($data[0])) {
                    foreach ($data as $item) {
                        if (is_array($item) && isset($item['field'], $item['message'])) {
                            $validationErrors[] = [
                                'field' => (string) $item['field'],
                                'message' => (string) $item['message'],
                            ];
                        }
                    }
                }
            }
        }

        if (empty($message) && $statusCode > 0) {
            $message = "AlyaPay API error: HTTP $statusCode";
        }

        return new AlyaPayApiException(
            $message,
            $statusCode,
            $key,
            $code,
            $parameters,
            $validationErrors,
            $body
        );
    }
}
