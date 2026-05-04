<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

class AlyaPayWebhookModuleFrontController extends ModuleFrontController
{
    public $auth = false;
    public $ajax = true;
    public $ssl = false;

    public function init()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->sendJson(200, ['success' => true, 'message' => 'Webhook endpoint active']);
        }

        $payload = file_get_contents('php://input');
        $signature = $_SERVER['HTTP_X_ALYA_SIGNATURE'] ?? null;
        $timestamp = $_SERVER['HTTP_X_ALYA_TIMESTAMP'] ?? null;

        $module = Module::getInstanceByName('alyapay');
        if (!$module) {
            $this->sendJson(200, ['success' => false, 'error' => 'Module not found']);
        }

        require_once $module->getLocalPath() . 'classes/AlyaPayConfig.php';
        require_once $module->getLocalPath() . 'classes/AlyaPaySignatureVerifier.php';
        require_once $module->getLocalPath() . 'classes/AlyaPayWebhookProcessor.php';
        require_once $module->getLocalPath() . 'classes/AlyaPayOrderHelper.php';
        require_once $module->getLocalPath() . 'classes/AlyaPayApiClient.php';
        require_once $module->getLocalPath() . 'classes/AlyaPayApiException.php';
        require_once $module->getLocalPath() . 'classes/AlyaPayStatusService.php';
        require_once $module->getLocalPath() . 'classes/AlyaPayErrorHandler.php';
        require_once $module->getLocalPath() . 'classes/AlyaPayMessageMap.php';
        require_once $module->getLocalPath() . 'classes/AlyaPayErrorContext.php';
        require_once $module->getLocalPath() . 'classes/AlyaPayErrorResult.php';

        $config = new AlyaPayConfig();
        $verifier = new AlyaPaySignatureVerifier($config);

        if (!$verifier->verify((string) $payload, $signature, $timestamp)) {
            PrestaShopLogger::addLog('AlyaPay webhook: signature verification failed', 2, null, 'AlyaPay');
            $this->sendJson(200, ['success' => false, 'error' => 'Invalid signature']);
        }

        $orderHelper = new AlyaPayOrderHelper();
        $processor = new AlyaPayWebhookProcessor($orderHelper, $config);
        $success = $processor->process((string) $payload);

        $this->sendJson(200, ['success' => $success]);
    }

    private function sendJson(int $statusCode, array $data): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }

    public function postProcess()
    {
    }

    public function initContent()
    {
    }

    public function display()
    {
    }
}
