<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

class AlyaPaySignatureVerifier
{
    private const MAX_AGE_SECONDS = 300;

    /** @var AlyaPayConfig */
    private $config;

    public function __construct(AlyaPayConfig $config)
    {
        $this->config = $config;
    }

    public function verify(string $payload, ?string $signatureHeader, ?string $timestampHeader): bool
    {
        $secret = $this->config->getWebhookSecret();
        if (empty($secret)) {
            PrestaShopLogger::addLog(
                'AlyaPay webhook: webhook_secret is not configured.',
                2,
                null,
                'AlyaPay'
            );
            return false;
        }

        if (empty($signatureHeader)) {
            PrestaShopLogger::addLog(
                'AlyaPay webhook: missing signature header.',
                2,
                null,
                'AlyaPay'
            );
            return false;
        }

        $expected = $this->computeSignature($payload, $timestampHeader, $secret);
        if ($expected === null) {
            return false;
        }

        $signature = $this->extractSignature($signatureHeader);
        if (!hash_equals($expected, $signature)) {
            PrestaShopLogger::addLog(
                'AlyaPay webhook: signature mismatch.',
                2,
                null,
                'AlyaPay'
            );
            return false;
        }

        return true;
    }

    private function extractSignature(string $header): string
    {
        if (strpos($header, 'sha256=') === 0) {
            return substr($header, 7);
        }
        return $header;
    }

    private function computeSignature(string $payload, ?string $timestamp, string $secret): ?string
    {
        if (!empty($timestamp)) {
            if (!$this->isTimestampValid($timestamp)) {
                PrestaShopLogger::addLog(
                    'AlyaPay webhook: timestamp too old or invalid: ' . $timestamp,
                    2,
                    null,
                    'AlyaPay'
                );
                return null;
            }
            $signedPayload = $timestamp . '.' . $payload;
        } else {
            $signedPayload = $payload;
        }

        return bin2hex(hash_hmac('sha256', $signedPayload, $secret, true));
    }

    private function isTimestampValid(string $timestamp): bool
    {
        if (!is_numeric($timestamp)) {
            try {
                $ts = (int) (new \DateTimeImmutable($timestamp))->getTimestamp();
            } catch (\Throwable $e) {
                return false;
            }
        } else {
            $ts = (int) $timestamp;
        }

        $now = time();
        return abs($now - $ts) <= self::MAX_AGE_SECONDS;
    }
}
