<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

class AlyaPayErrorHandler
{
    /** @var AlyaPayMessageMap */
    private $messageMap;

    public function __construct(AlyaPayMessageMap $messageMap)
    {
        $this->messageMap = $messageMap;
    }

    public function handle(Throwable $e, string $context, array $extra = []): AlyaPayErrorResult
    {
        $extra = $this->enrichExtraFromException($e, $extra);
        [$userMessage, $audience] = $this->messageMap->getMessageAndAudience($e, $context, $extra);
        $logMessage = $this->buildLogMessage($e, $context, $extra);

        PrestaShopLogger::addLog(
            '[AlyaPay][' . $context . '] ' . $logMessage,
            3,
            $extra['status'] ?? null,
            'AlyaPay'
        );

        return new AlyaPayErrorResult($userMessage, $logMessage, AlyaPayErrorResult::SEVERITY_ERROR, $audience);
    }

    private function enrichExtraFromException(Throwable $e, array $extra): array
    {
        if ($e instanceof AlyaPayApiException) {
            $extra['status'] = $e->getStatusCode();
            $extra['code'] = $e->getApiCode();
            $extra['key'] = $e->getKey();
            $extra['validationErrors'] = $e->getValidationErrors();
        }
        return $extra;
    }

    private function buildLogMessage(Throwable $e, string $context, array $extra): string
    {
        $parts = ['context=' . $context];
        if (isset($extra['status'])) {
            $parts[] = 'status=' . $extra['status'];
        }
        if (isset($extra['code'])) {
            $parts[] = 'code=' . $extra['code'];
        }
        if (isset($extra['key'])) {
            $parts[] = 'key=' . $extra['key'];
        }
        if (count($parts) > 1) {
            return implode(' ', $parts);
        }
        $msg = $e->getMessage();
        return strlen($msg) > 200 ? substr($msg, 0, 200) . '...' : $msg;
    }
}
