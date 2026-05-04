<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

class AlyaPayMessageMap
{
    private const AUDIENCE_ADMIN = AlyaPayErrorResult::AUDIENCE_ADMIN;
    private const AUDIENCE_CUSTOMER = AlyaPayErrorResult::AUDIENCE_CUSTOMER;

    private const STATUS_MAP = [
        AlyaPayErrorContext::PARTNER_CONFIG => [
            401 => [self::AUDIENCE_ADMIN, 'alyapay.error.invalid_api_key'],
            400 => [self::AUDIENCE_ADMIN, 'alyapay.error.invalid_request'],
            500 => [self::AUDIENCE_ADMIN, 'alyapay.error.config_sync_failed'],
        ],
        AlyaPayErrorContext::SESSION_INTENT => [
            401 => [self::AUDIENCE_ADMIN, 'alyapay.error.invalid_api_key'],
            400 => [self::AUDIENCE_ADMIN, 'alyapay.error.invalid_request'],
            405 => [self::AUDIENCE_ADMIN, 'alyapay.error.invalid_request'],
            415 => [self::AUDIENCE_ADMIN, 'alyapay.error.invalid_request'],
            500 => [self::AUDIENCE_ADMIN, 'alyapay.error.payment_unavailable'],
        ],
        AlyaPayErrorContext::STATUS_CHECK => [
            401 => [self::AUDIENCE_ADMIN, 'alyapay.error.invalid_api_key'],
            400 => [self::AUDIENCE_ADMIN, 'alyapay.error.transaction_not_found'],
            500 => [self::AUDIENCE_ADMIN, 'alyapay.error.status_check_failed'],
        ],
    ];

    private const CODE_MAP = [
        AlyaPayErrorContext::SESSION_INTENT => [
            4235 => [self::AUDIENCE_ADMIN, 'alyapay.error.invalid_order_data'],
            4239 => [self::AUDIENCE_CUSTOMER, 'alyapay.error.amount_too_low'],
            4242 => [self::AUDIENCE_CUSTOMER, 'alyapay.error.amount_too_high'],
        ],
        AlyaPayErrorContext::STATUS_CHECK => [
            4042 => [self::AUDIENCE_ADMIN, 'alyapay.error.transaction_not_found'],
        ],
        AlyaPayErrorContext::PARTNER_CONFIG => [],
    ];

    private const GENERIC_KEY = 'alyapay.error.generic';

    private const MESSAGES = [
        'alyapay.error.invalid_api_key' => 'Invalid API key. Please check your AlyaPay configuration.',
        'alyapay.error.invalid_order_data' => 'Invalid order data. Please check your integration.',
        'alyapay.error.amount_too_low' => 'The order amount is below the minimum for AlyaPay.',
        'alyapay.error.amount_too_high' => 'The order amount exceeds the maximum for AlyaPay.',
        'alyapay.error.validation_failed' => 'Validation failed: %s',
        'alyapay.error.invalid_request' => 'Invalid request. Please check your configuration.',
        'alyapay.error.payment_unavailable' => 'Payment is temporarily unavailable. Please try again later.',
        'alyapay.error.transaction_not_found' => 'Transaction not found.',
        'alyapay.error.status_check_failed' => 'Unable to verify payment status. Please contact support.',
        'alyapay.error.config_sync_failed' => 'Unable to sync configuration to AlyaPay. Please try again later.',
        'alyapay.error.generic' => 'A payment error occurred. Please try again or contact support.',
    ];

    /**
     * @return array{0: string, 1: string} [userMessage, audience]
     */
    public function getMessageAndAudience(Throwable $e, string $context, array $extra = []): array
    {
        $status = $extra['status'] ?? null;
        $code = $extra['code'] ?? null;
        $validationErrors = $extra['validationErrors'] ?? [];

        if ($e instanceof AlyaPayApiException) {
            $status = $e->getStatusCode();
            $code = $e->getApiCode();
            $validationErrors = $e->getValidationErrors();
        }

        if ($status === null) {
            $status = $this->extractStatusFromException($e);
        }

        $map = self::STATUS_MAP[$context] ?? [];
        $codeMap = self::CODE_MAP[$context] ?? [];

        if ($code !== null && isset($codeMap[$code])) {
            [$audience, $key] = $codeMap[$code];
            return [$this->translate($key), $audience];
        }

        if (!empty($validationErrors)) {
            $last = end($validationErrors);
            $field = $last['field'] ?? '';
            $msg = $last['message'] ?? '';
            $text = trim($field ? "$field: $msg" : $msg);
            return [
                sprintf(self::MESSAGES['alyapay.error.validation_failed'], $text),
                self::AUDIENCE_ADMIN,
            ];
        }

        if ($status !== null && isset($map[$status])) {
            [$audience, $key] = $map[$status];
            return [$this->translate($key), $audience];
        }

        return [$this->translate(self::GENERIC_KEY), self::AUDIENCE_ADMIN];
    }

    public function getUserMessage(Throwable $e, string $context, ?int $status = null): string
    {
        $extra = $status !== null ? ['status' => $status] : [];
        [$message] = $this->getMessageAndAudience($e, $context, $extra);
        return $message;
    }

    private function translate(string $key): string
    {
        return self::MESSAGES[$key] ?? $key;
    }

    private function extractStatusFromException(Throwable $e): ?int
    {
        $message = $e->getMessage();
        if (preg_match('/\b(401|400|403|404|405|415|422|500|502|503)\b/', $message, $m)) {
            return (int) $m[1];
        }
        return null;
    }
}
