<?php

$canonicalHost = getenv('ALYAPAY_CANONICAL_HOST') ?: getenv('PS_DOMAIN');
if (!empty($canonicalHost)) {
    $canonicalHost = preg_replace('#^https?://#i', '', trim($canonicalHost));
    $canonicalHost = rtrim($canonicalHost, '/');
    if ($canonicalHost !== '') {
        $_SERVER['HTTP_HOST'] = $canonicalHost;
    }
}

require_once dirname(__FILE__) . '/../../config/config.inc.php';
require_once dirname(__FILE__) . '/classes/AlyaPayConfig.php';
require_once dirname(__FILE__) . '/classes/AlyaPaySignatureVerifier.php';
require_once dirname(__FILE__) . '/classes/AlyaPayWebhookProcessor.php';
require_once dirname(__FILE__) . '/classes/AlyaPayOrderHelper.php';
require_once dirname(__FILE__) . '/classes/AlyaPayEmailQueue.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $payload = file_get_contents('php://input');
    $signature = $_SERVER['HTTP_X_ALYA_SIGNATURE'] ?? null;
    $timestamp = $_SERVER['HTTP_X_ALYA_TIMESTAMP'] ?? null;

    $config = new AlyaPayConfig();
    $verifier = new AlyaPaySignatureVerifier($config);

    if ($verifier->verify((string) $payload, $signature, $timestamp)) {
        try {
            $processor = new AlyaPayWebhookProcessor(new AlyaPayOrderHelper(), $config);
            $processor->process((string) $payload);
        } catch (Throwable $e) {
            PrestaShopLogger::addLog('AlyaPay webhook: ' . $e->getMessage(), 3, null, 'AlyaPay');
        }
    } else {
        PrestaShopLogger::addLog('AlyaPay webhook: invalid signature', 2, null, 'AlyaPay');
    }
}

http_response_code(200);
exit;
