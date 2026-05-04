<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once __DIR__ . '/classes/AlyaPayConfig.php';
require_once __DIR__ . '/classes/AlyaPayApiClient.php';
require_once __DIR__ . '/classes/AlyaPayApiException.php';
require_once __DIR__ . '/classes/AlyaPayErrorHandler.php';
require_once __DIR__ . '/classes/AlyaPayMessageMap.php';
require_once __DIR__ . '/classes/AlyaPayErrorContext.php';
require_once __DIR__ . '/classes/AlyaPayErrorResult.php';
require_once __DIR__ . '/classes/AlyaPaySessionIntentService.php';
require_once __DIR__ . '/classes/AlyaPayStatusService.php';
require_once __DIR__ . '/classes/AlyaPayScheduleService.php';
require_once __DIR__ . '/classes/AlyaPayPartnerConfigService.php';
require_once __DIR__ . '/classes/AlyaPayWebhookProcessor.php';
require_once __DIR__ . '/classes/AlyaPaySignatureVerifier.php';
require_once __DIR__ . '/classes/AlyaPayOrderHelper.php';

class AlyaPay extends PaymentModule
{
    public function __construct()
    {
        $this->name = 'alyapay';
        $this->tab = 'payments_gateways';
        $this->version = '1.0.0';
        $this->author = 'AlyaPay';
        $this->need_instance = 0;
        $this->ps_versions_compliancy = ['min' => '1.7.0.0', 'max' => _PS_VERSION_];
        $this->controllers = ['redirect', 'success', 'cancel', 'webhook'];
        $this->is_eu_compatible = 1;
        $this->currencies = true;
        $this->currencies_mode = 'checkbox';
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('AlyaPay');
        $this->description = $this->l('Pay in installments with AlyaPay.');
        $this->confirmUninstall = $this->l('Are you sure you want to uninstall AlyaPay?');
    }

    public function install()
    {
        if (!parent::install()) {
            return false;
        }

        $hooks = [
            'paymentOptions',
            'displayPaymentReturn',
            'displayProductAdditionalInfo',
            'displayShoppingCartFooter',
            'actionAdminControllerSetMedia',
            'actionFrontControllerSetMedia',
        ];

        foreach ($hooks as $hook) {
            if (!$this->registerHook($hook)) {
                return false;
            }
        }

        include __DIR__ . '/sql/install.php';

        $defaults = [
            'ALYAPAY_ACTIVE' => '0',
            'ALYAPAY_TITLE' => 'AlyaPay',
            'ALYAPAY_API_BASE_URL' => 'https://sandbox-api.alyapay.com',
            'ALYAPAY_DEBUG' => '0',
            'ALYAPAY_TRANSACTION_EXPIRY' => '30',
            'ALYAPAY_AMOUNT_MIN' => '500',
            'ALYAPAY_AMOUNT_MAX' => '15000',
            'ALYAPAY_STATUS_APPROVED' => (string) Configuration::get('PS_OS_PAYMENT'),
            'ALYAPAY_STATUS_CANCELED' => (string) Configuration::get('PS_OS_CANCELED'),
            'ALYAPAY_STATUS_EXPIRED' => (string) Configuration::get('PS_OS_CANCELED'),
            'ALYAPAY_WIDGET_CHECKOUT_ENABLED' => '0',
            'ALYAPAY_WIDGET_THEME' => 'light',
            'ALYAPAY_WIDGET_VARIANT' => 'default',
            'ALYAPAY_WIDGET_DETAIL' => 'modal',
            'ALYAPAY_WIDGET_LOGO_POSITION' => 'right',
            'ALYAPAY_WIDGET_FULL_WIDTH' => '0',
            'ALYAPAY_WIDGET_MARGIN_X' => '',
            'ALYAPAY_WIDGET_MARGIN_Y' => '',
            'ALYAPAY_WIDGET_PADDING_X' => '',
            'ALYAPAY_WIDGET_PADDING_Y' => '',
            'ALYAPAY_WIDGET_CURRENCY' => 'MAD',
            'ALYAPAY_WIDGET_PRODUCT_ENABLED' => '0',
            'ALYAPAY_PRODUCT_WIDGET_THEME' => 'light',
            'ALYAPAY_PRODUCT_WIDGET_VARIANT' => 'default',
            'ALYAPAY_PRODUCT_WIDGET_DETAIL' => 'modal',
            'ALYAPAY_PRODUCT_WIDGET_LOGO_POSITION' => 'right',
            'ALYAPAY_PRODUCT_WIDGET_FULL_WIDTH' => '0',
            'ALYAPAY_PRODUCT_WIDGET_MARGIN_X' => '',
            'ALYAPAY_PRODUCT_WIDGET_MARGIN_Y' => '',
            'ALYAPAY_PRODUCT_WIDGET_PADDING_X' => '',
            'ALYAPAY_PRODUCT_WIDGET_PADDING_Y' => '',
            'ALYAPAY_WIDGET_CART_ENABLED' => '0',
            'ALYAPAY_CART_WIDGET_THEME' => 'light',
            'ALYAPAY_CART_WIDGET_VARIANT' => 'default',
            'ALYAPAY_CART_WIDGET_DETAIL' => 'modal',
            'ALYAPAY_CART_WIDGET_LOGO_POSITION' => 'right',
            'ALYAPAY_CART_WIDGET_FULL_WIDTH' => '0',
            'ALYAPAY_CART_WIDGET_MARGIN_X' => '',
            'ALYAPAY_CART_WIDGET_MARGIN_Y' => '',
            'ALYAPAY_CART_WIDGET_PADDING_X' => '',
            'ALYAPAY_CART_WIDGET_PADDING_Y' => '',
        ];

        foreach ($defaults as $key => $value) {
            if (Configuration::get($key) === false) {
                Configuration::updateValue($key, $value);
            }
        }

        return true;
    }

    public function uninstall()
    {
        include __DIR__ . '/sql/uninstall.php';
        return parent::uninstall();
    }

    // ─── Admin configuration ───────────────────────────────────────────

    public function getContent()
    {
        $output = '';

        if (Tools::isSubmit('submitAlyaPayConfig')) {
            $output .= $this->postProcess();
        }

        return $output . $this->renderForm();
    }

    private function postProcess(): string
    {
        $output = '';
        $fields = $this->getConfigFields();

        foreach ($fields as $key => $meta) {
            $value = Tools::getValue($key, Configuration::get($key));
            if ($meta['type'] === 'password' && empty($value)) {
                continue;
            }
            Configuration::updateValue($key, $value);
        }

        $output .= $this->displayConfirmation($this->l('Settings saved.'));

        $syncWarning = $this->syncPartnerConfig();
        if ($syncWarning) {
            $output .= $this->displayWarning($syncWarning);
        }

        return $output;
    }

    private function syncPartnerConfig(): ?string
    {
        $config = new AlyaPayConfig();
        if (!$config->isActive() || !$config->getApiKey()) {
            return null;
        }

        $webhookUrl = $config->getWebhookUrl();
        if (empty(trim($webhookUrl))) {
            return null;
        }

        $payload = [
            'webhookUrl' => $webhookUrl,
            'webhookEnabled' => true,
            'transactionExpiry' => $config->getTransactionExpiry(),
            'generateNewSecret' => false,
        ];

        try {
            $service = new AlyaPayPartnerConfigService(
                new AlyaPayApiClient($config),
                $config
            );
            $service->updateConfig($payload);
        } catch (\Throwable $e) {
            $handler = new AlyaPayErrorHandler(new AlyaPayMessageMap());
            $result = $handler->handle($e, AlyaPayErrorContext::PARTNER_CONFIG);
            PrestaShopLogger::addLog(
                'AlyaPay config sync failed: ' . $result->getLogMessage(),
                3,
                null,
                'AlyaPay'
            );

            $detail = $result->getUserMessage();
            if ($e instanceof AlyaPayApiException && !empty($e->getValidationErrors())) {
                $parts = [];
                foreach ($e->getValidationErrors() as $ve) {
                    $parts[] = $ve['field'] . ': ' . $ve['message'];
                }
                $detail = implode('; ', $parts);
            }
            return $this->l('Partner config sync failed:') . ' ' . $detail;
        }

        return null;
    }

    private function getConfigFields(): array
    {
        return [
            'ALYAPAY_ACTIVE' => ['type' => 'switch', 'label' => $this->l('Enabled')],
            'ALYAPAY_TITLE' => ['type' => 'text', 'label' => $this->l('Title')],
            'ALYAPAY_DEBUG' => ['type' => 'switch', 'label' => $this->l('Debug Mode')],
            'ALYAPAY_TRANSACTION_EXPIRY' => ['type' => 'text', 'label' => $this->l('Transaction Expiry (minutes)')],
            'ALYAPAY_AMOUNT_MIN' => ['type' => 'text', 'label' => $this->l('Minimum Order Amount')],
            'ALYAPAY_AMOUNT_MAX' => ['type' => 'text', 'label' => $this->l('Maximum Order Amount')],
            'ALYAPAY_API_BASE_URL' => ['type' => 'text', 'label' => $this->l('API Base URL')],
            'ALYAPAY_API_KEY' => ['type' => 'password', 'label' => $this->l('API Key')],
            'ALYAPAY_WEBHOOK_URL' => ['type' => 'text', 'label' => $this->l('Your Store URL')],
            'ALYAPAY_WEBHOOK_SECRET' => ['type' => 'password', 'label' => $this->l('Webhook Secret')],
            'ALYAPAY_STATUS_APPROVED' => ['type' => 'select_order_state', 'label' => $this->l('Status for Approved')],
            'ALYAPAY_STATUS_CANCELED' => ['type' => 'select_order_state', 'label' => $this->l('Status for Canceled')],
            'ALYAPAY_STATUS_EXPIRED' => ['type' => 'select_order_state', 'label' => $this->l('Status for Expired')],
            'ALYAPAY_WIDGET_CHECKOUT_ENABLED' => ['type' => 'switch', 'label' => $this->l('Enable Checkout Widget')],
            'ALYAPAY_WIDGET_CURRENCY' => ['type' => 'text', 'label' => $this->l('Widget Currency')],
            'ALYAPAY_WIDGET_THEME' => ['type' => 'select_theme', 'label' => $this->l('Widget Theme')],
            'ALYAPAY_WIDGET_VARIANT' => ['type' => 'select_variant', 'label' => $this->l('Widget Variant')],
            'ALYAPAY_WIDGET_DETAIL' => ['type' => 'select_detail', 'label' => $this->l('Widget Detail')],
            'ALYAPAY_WIDGET_LOGO_POSITION' => ['type' => 'select_logo_pos', 'label' => $this->l('Widget Logo Position')],
            'ALYAPAY_WIDGET_FULL_WIDTH' => ['type' => 'switch', 'label' => $this->l('Full Width')],
            'ALYAPAY_WIDGET_MARGIN_X' => ['type' => 'text', 'label' => $this->l('Margin X (px)')],
            'ALYAPAY_WIDGET_MARGIN_Y' => ['type' => 'text', 'label' => $this->l('Margin Y (px)')],
            'ALYAPAY_WIDGET_PADDING_X' => ['type' => 'text', 'label' => $this->l('Padding X (px)')],
            'ALYAPAY_WIDGET_PADDING_Y' => ['type' => 'text', 'label' => $this->l('Padding Y (px)')],
            'ALYAPAY_WIDGET_PRODUCT_ENABLED' => ['type' => 'switch', 'label' => $this->l('Enable Credit Promo on Product Page')],
            'ALYAPAY_PRODUCT_WIDGET_THEME' => ['type' => 'select_theme', 'label' => $this->l('Product Widget Theme')],
            'ALYAPAY_PRODUCT_WIDGET_VARIANT' => ['type' => 'select_variant', 'label' => $this->l('Product Widget Variant')],
            'ALYAPAY_PRODUCT_WIDGET_DETAIL' => ['type' => 'select_detail', 'label' => $this->l('Product Widget Detail')],
            'ALYAPAY_PRODUCT_WIDGET_LOGO_POSITION' => ['type' => 'select_logo_pos', 'label' => $this->l('Product Widget Logo Position')],
            'ALYAPAY_PRODUCT_WIDGET_FULL_WIDTH' => ['type' => 'switch', 'label' => $this->l('Full Width')],
            'ALYAPAY_PRODUCT_WIDGET_MARGIN_X' => ['type' => 'text', 'label' => $this->l('Margin X (px)')],
            'ALYAPAY_PRODUCT_WIDGET_MARGIN_Y' => ['type' => 'text', 'label' => $this->l('Margin Y (px)')],
            'ALYAPAY_PRODUCT_WIDGET_PADDING_X' => ['type' => 'text', 'label' => $this->l('Padding X (px)')],
            'ALYAPAY_PRODUCT_WIDGET_PADDING_Y' => ['type' => 'text', 'label' => $this->l('Padding Y (px)')],
            'ALYAPAY_WIDGET_CART_ENABLED' => ['type' => 'switch', 'label' => $this->l('Enable Credit Promo on Cart')],
            'ALYAPAY_CART_WIDGET_THEME' => ['type' => 'select_theme', 'label' => $this->l('Cart Widget Theme')],
            'ALYAPAY_CART_WIDGET_VARIANT' => ['type' => 'select_variant', 'label' => $this->l('Cart Widget Variant')],
            'ALYAPAY_CART_WIDGET_DETAIL' => ['type' => 'select_detail', 'label' => $this->l('Cart Widget Detail')],
            'ALYAPAY_CART_WIDGET_LOGO_POSITION' => ['type' => 'select_logo_pos', 'label' => $this->l('Cart Widget Logo Position')],
            'ALYAPAY_CART_WIDGET_FULL_WIDTH' => ['type' => 'switch', 'label' => $this->l('Full Width')],
            'ALYAPAY_CART_WIDGET_MARGIN_X' => ['type' => 'text', 'label' => $this->l('Margin X (px)')],
            'ALYAPAY_CART_WIDGET_MARGIN_Y' => ['type' => 'text', 'label' => $this->l('Margin Y (px)')],
            'ALYAPAY_CART_WIDGET_PADDING_X' => ['type' => 'text', 'label' => $this->l('Padding X (px)')],
            'ALYAPAY_CART_WIDGET_PADDING_Y' => ['type' => 'text', 'label' => $this->l('Padding Y (px)')],
        ];
    }

    private function renderPasswordField(string $name, bool $hasValue, string $emptyDesc, string $setDesc): string
    {
        $valueJs = $hasValue ? json_encode((string) Configuration::get($name)) : 'null';
        $desc    = $hasValue ? $setDesc : $emptyDesc;
        $eyeId   = 'alya_eye_' . strtolower($name);

        return '<style>.alya-pw-masked{-webkit-text-security:disc;letter-spacing:.05em;}</style>'
            . '<div class="input-group" style="max-width:420px">'
            . '<input type="text" id="' . $name . '" name="' . $name . '" class="form-control' . ($hasValue ? ' alya-pw-masked' : '') . '" autocomplete="off" />'
            . '<span class="input-group-btn">'
            . '<button type="button" id="' . $eyeId . '" class="btn btn-default" title="' . $this->l('Toggle visibility') . '">'
            . '<i class="icon-eye"></i>'
            . '</button>'
            . '</span>'
            . '</div>'
            . '<p class="help-block">' . htmlspecialchars($desc) . '</p>'
            . '<script>(function(){'
            . 'var f=document.getElementById(' . json_encode($name) . '),'
            . 'b=document.getElementById(' . json_encode($eyeId) . '),'
            . 'v=' . $valueJs . ';'
            . 'if(f&&v)document.addEventListener("DOMContentLoaded",function(){f.value=v;});'
            . 'if(b&&f)b.addEventListener("click",function(){'
            . 'var masked=f.classList.toggle("alya-pw-masked");'
            . 'b.querySelector("i").className=masked?"icon-eye":"icon-eye-slash";'
            . '});'
            . '})();</script>';
    }

    private function renderForm(): string
    {
        $orderStates = OrderState::getOrderStates($this->context->language->id);
        $stateOptions = [];
        foreach ($orderStates as $state) {
            $stateOptions[] = ['id_option' => $state['id_order_state'], 'name' => $state['name']];
        }

        $themeOptions = [
            ['id_option' => 'light', 'name' => 'Light'],
            ['id_option' => 'light-plain', 'name' => 'Light Plain'],
            ['id_option' => 'dark', 'name' => 'Dark'],
            ['id_option' => 'dark-plain', 'name' => 'Dark Plain'],
            ['id_option' => 'neutral', 'name' => 'Neutral'],
            ['id_option' => 'neutral-plain', 'name' => 'Neutral Plain'],
        ];
        $variantOptions = [
            ['id_option' => 'default', 'name' => 'Default'],
            ['id_option' => 'interactive', 'name' => 'Interactive'],
        ];
        $detailOptions = [
            ['id_option' => 'modal', 'name' => 'Modal'],
            ['id_option' => 'panel', 'name' => 'Panel'],
        ];
        $logoPosOptions = [
            ['id_option' => 'right', 'name' => 'Right'],
            ['id_option' => 'left', 'name' => 'Left'],
        ];
        $switchValues = [
            ['id' => 'active_on', 'value' => 1, 'label' => $this->l('Yes')],
            ['id' => 'active_off', 'value' => 0, 'label' => $this->l('No')],
        ];

        $fields_form = [
            // General
            [
                'form' => [
                    'legend' => ['title' => $this->l('General'), 'icon' => 'icon-cogs'],
                    'input' => [
                        ['type' => 'switch', 'label' => $this->l('Enabled'), 'name' => 'ALYAPAY_ACTIVE', 'values' => $switchValues],
                        ['type' => 'text', 'label' => $this->l('Title'), 'name' => 'ALYAPAY_TITLE', 'size' => 40],
                        ['type' => 'switch', 'label' => $this->l('Debug Mode'), 'name' => 'ALYAPAY_DEBUG', 'values' => $switchValues],
                        ['type' => 'text', 'label' => $this->l('Transaction Expiry (minutes)'), 'name' => 'ALYAPAY_TRANSACTION_EXPIRY', 'size' => 10, 'desc' => $this->l('0 = no expiry, 30 = default. Max 60.')],
                        ['type' => 'text', 'label' => $this->l('Minimum Order Amount'), 'name' => 'ALYAPAY_AMOUNT_MIN', 'size' => 10, 'disabled' => true, 'desc' => $this->l('Set by AlyaPay. Contact support to change.')],
                        ['type' => 'text', 'label' => $this->l('Maximum Order Amount'), 'name' => 'ALYAPAY_AMOUNT_MAX', 'size' => 10, 'disabled' => true, 'desc' => $this->l('Set by AlyaPay. Contact support to change.')],
                    ],
                ],
            ],
            // API
            [
                'form' => [
                    'legend' => ['title' => $this->l('API'), 'icon' => 'icon-key'],
                    'input' => [
                        ['type' => 'text', 'label' => $this->l('API Base URL'), 'name' => 'ALYAPAY_API_BASE_URL', 'size' => 60, 'desc' => $this->l('Sandbox: https://sandbox-api.alyapay.com | Production: https://api.alyapay.com')],
                        ['type' => 'html', 'name' => 'ALYAPAY_API_KEY_HTML', 'label' => $this->l('API Key'), 'html_content' => $this->renderPasswordField('ALYAPAY_API_KEY', (bool) Configuration::get('ALYAPAY_API_KEY'), $this->l('Enter your AlyaPay API key.'), $this->l('Key saved. Leave empty to keep current value.'))],
                    ],
                ],
            ],
            // Webhooks
            [
                'form' => [
                    'legend' => ['title' => $this->l('Webhooks'), 'icon' => 'icon-exchange'],
                    'input' => [
                        ['type' => 'text', 'label' => $this->l('Your Store URL'), 'name' => 'ALYAPAY_WEBHOOK_URL', 'size' => 60, 'desc' => $this->l('Enter your store URL (e.g. https://your-store.com). The webhook path is appended automatically.')],
                        ['type' => 'html', 'name' => 'ALYAPAY_WEBHOOK_SECRET_HTML', 'label' => $this->l('Webhook Secret'), 'html_content' => $this->renderPasswordField('ALYAPAY_WEBHOOK_SECRET', (bool) Configuration::get('ALYAPAY_WEBHOOK_SECRET'), $this->l('HMAC-SHA256 secret for webhook signature verification.'), $this->l('Secret saved. Leave empty to keep current value.'))],
                    ],
                ],
            ],
            // Order Status Mapping
            [
                'form' => [
                    'legend' => ['title' => $this->l('Order Status Mapping'), 'icon' => 'icon-random'],
                    'input' => [
                        ['type' => 'select', 'label' => $this->l('Status for Approved'), 'name' => 'ALYAPAY_STATUS_APPROVED', 'options' => ['query' => $stateOptions, 'id' => 'id_option', 'name' => 'name']],
                        ['type' => 'select', 'label' => $this->l('Status for Canceled'), 'name' => 'ALYAPAY_STATUS_CANCELED', 'options' => ['query' => $stateOptions, 'id' => 'id_option', 'name' => 'name']],
                        ['type' => 'select', 'label' => $this->l('Status for Expired'), 'name' => 'ALYAPAY_STATUS_EXPIRED', 'options' => ['query' => $stateOptions, 'id' => 'id_option', 'name' => 'name']],
                    ],
                ],
            ],
            // Checkout Widget
            [
                'form' => [
                    'legend' => ['title' => $this->l('Checkout Widget'), 'icon' => 'icon-shopping-cart'],
                    'input' => [
                        ['type' => 'switch', 'label' => $this->l('Enable Checkout Widget'), 'name' => 'ALYAPAY_WIDGET_CHECKOUT_ENABLED', 'values' => $switchValues],
                        ['type' => 'text', 'label' => $this->l('Widget Currency'), 'name' => 'ALYAPAY_WIDGET_CURRENCY', 'size' => 10, 'desc' => $this->l('e.g. MAD, Dhs. Leave empty for store currency.')],
                        ['type' => 'select', 'label' => $this->l('Theme'), 'name' => 'ALYAPAY_WIDGET_THEME', 'options' => ['query' => $themeOptions, 'id' => 'id_option', 'name' => 'name']],
                        ['type' => 'select', 'label' => $this->l('Variant'), 'name' => 'ALYAPAY_WIDGET_VARIANT', 'options' => ['query' => $variantOptions, 'id' => 'id_option', 'name' => 'name']],
                        ['type' => 'select', 'label' => $this->l('Detail'), 'name' => 'ALYAPAY_WIDGET_DETAIL', 'options' => ['query' => $detailOptions, 'id' => 'id_option', 'name' => 'name']],
                        ['type' => 'select', 'label' => $this->l('Logo Position'), 'name' => 'ALYAPAY_WIDGET_LOGO_POSITION', 'options' => ['query' => $logoPosOptions, 'id' => 'id_option', 'name' => 'name']],
                        ['type' => 'switch', 'label' => $this->l('Full Width'), 'name' => 'ALYAPAY_WIDGET_FULL_WIDTH', 'values' => $switchValues, 'desc' => $this->l('Stretch widget to full container width.')],
                        ['type' => 'text', 'label' => $this->l('Margin X (px)'), 'name' => 'ALYAPAY_WIDGET_MARGIN_X', 'size' => 6, 'placeholder' => '0', 'desc' => $this->l('Left and right margin. Default: 0.')],
                        ['type' => 'text', 'label' => $this->l('Margin Y (px)'), 'name' => 'ALYAPAY_WIDGET_MARGIN_Y', 'size' => 6, 'placeholder' => '0', 'desc' => $this->l('Top and bottom margin. Default: 0.')],
                        ['type' => 'text', 'label' => $this->l('Padding X (px)'), 'name' => 'ALYAPAY_WIDGET_PADDING_X', 'size' => 6, 'placeholder' => '18', 'desc' => $this->l('Left and right inner padding. Default: 18.')],
                        ['type' => 'text', 'label' => $this->l('Padding Y (px)'), 'name' => 'ALYAPAY_WIDGET_PADDING_Y', 'size' => 6, 'placeholder' => '14', 'desc' => $this->l('Top and bottom inner padding. Default: 14.')],
                    ],
                ],
            ],
            // Product Page Widget
            [
                'form' => [
                    'legend' => ['title' => $this->l('Product Page Widget'), 'icon' => 'icon-tag'],
                    'input' => [
                        ['type' => 'switch', 'label' => $this->l('Enable Credit Promo on Product Page'), 'name' => 'ALYAPAY_WIDGET_PRODUCT_ENABLED', 'values' => $switchValues],
                        ['type' => 'select', 'label' => $this->l('Theme'), 'name' => 'ALYAPAY_PRODUCT_WIDGET_THEME', 'options' => ['query' => $themeOptions, 'id' => 'id_option', 'name' => 'name']],
                        ['type' => 'select', 'label' => $this->l('Variant'), 'name' => 'ALYAPAY_PRODUCT_WIDGET_VARIANT', 'options' => ['query' => $variantOptions, 'id' => 'id_option', 'name' => 'name']],
                        ['type' => 'select', 'label' => $this->l('Detail'), 'name' => 'ALYAPAY_PRODUCT_WIDGET_DETAIL', 'options' => ['query' => $detailOptions, 'id' => 'id_option', 'name' => 'name']],
                        ['type' => 'select', 'label' => $this->l('Logo Position'), 'name' => 'ALYAPAY_PRODUCT_WIDGET_LOGO_POSITION', 'options' => ['query' => $logoPosOptions, 'id' => 'id_option', 'name' => 'name']],
                        ['type' => 'switch', 'label' => $this->l('Full Width'), 'name' => 'ALYAPAY_PRODUCT_WIDGET_FULL_WIDTH', 'values' => $switchValues, 'desc' => $this->l('Stretch widget to full container width.')],
                        ['type' => 'text', 'label' => $this->l('Margin X (px)'), 'name' => 'ALYAPAY_PRODUCT_WIDGET_MARGIN_X', 'size' => 6, 'placeholder' => '0', 'desc' => $this->l('Left and right margin. Default: 0.')],
                        ['type' => 'text', 'label' => $this->l('Margin Y (px)'), 'name' => 'ALYAPAY_PRODUCT_WIDGET_MARGIN_Y', 'size' => 6, 'placeholder' => '0', 'desc' => $this->l('Top and bottom margin. Default: 0.')],
                        ['type' => 'text', 'label' => $this->l('Padding X (px)'), 'name' => 'ALYAPAY_PRODUCT_WIDGET_PADDING_X', 'size' => 6, 'placeholder' => '18', 'desc' => $this->l('Left and right inner padding. Default: 18.')],
                        ['type' => 'text', 'label' => $this->l('Padding Y (px)'), 'name' => 'ALYAPAY_PRODUCT_WIDGET_PADDING_Y', 'size' => 6, 'placeholder' => '14', 'desc' => $this->l('Top and bottom inner padding. Default: 14.')],
                    ],
                ],
            ],
            // Cart Widget
            [
                'form' => [
                    'legend' => ['title' => $this->l('Cart Widget'), 'icon' => 'icon-shopping-bag'],
                    'input' => [
                        ['type' => 'switch', 'label' => $this->l('Enable Credit Promo on Cart'), 'name' => 'ALYAPAY_WIDGET_CART_ENABLED', 'values' => $switchValues],
                        ['type' => 'select', 'label' => $this->l('Theme'), 'name' => 'ALYAPAY_CART_WIDGET_THEME', 'options' => ['query' => $themeOptions, 'id' => 'id_option', 'name' => 'name']],
                        ['type' => 'select', 'label' => $this->l('Variant'), 'name' => 'ALYAPAY_CART_WIDGET_VARIANT', 'options' => ['query' => $variantOptions, 'id' => 'id_option', 'name' => 'name']],
                        ['type' => 'select', 'label' => $this->l('Detail'), 'name' => 'ALYAPAY_CART_WIDGET_DETAIL', 'options' => ['query' => $detailOptions, 'id' => 'id_option', 'name' => 'name']],
                        ['type' => 'select', 'label' => $this->l('Logo Position'), 'name' => 'ALYAPAY_CART_WIDGET_LOGO_POSITION', 'options' => ['query' => $logoPosOptions, 'id' => 'id_option', 'name' => 'name']],
                        ['type' => 'switch', 'label' => $this->l('Full Width'), 'name' => 'ALYAPAY_CART_WIDGET_FULL_WIDTH', 'values' => $switchValues, 'desc' => $this->l('Stretch widget to full container width.')],
                        ['type' => 'text', 'label' => $this->l('Margin X (px)'), 'name' => 'ALYAPAY_CART_WIDGET_MARGIN_X', 'size' => 6, 'placeholder' => '0', 'desc' => $this->l('Left and right margin. Default: 0.')],
                        ['type' => 'text', 'label' => $this->l('Margin Y (px)'), 'name' => 'ALYAPAY_CART_WIDGET_MARGIN_Y', 'size' => 6, 'placeholder' => '0', 'desc' => $this->l('Top and bottom margin. Default: 0.')],
                        ['type' => 'text', 'label' => $this->l('Padding X (px)'), 'name' => 'ALYAPAY_CART_WIDGET_PADDING_X', 'size' => 6, 'placeholder' => '18', 'desc' => $this->l('Left and right inner padding. Default: 18.')],
                        ['type' => 'text', 'label' => $this->l('Padding Y (px)'), 'name' => 'ALYAPAY_CART_WIDGET_PADDING_Y', 'size' => 6, 'placeholder' => '14', 'desc' => $this->l('Top and bottom inner padding. Default: 14.')],
                    ],
                    'submit' => ['title' => $this->l('Save'), 'class' => 'btn btn-default pull-right'],
                ],
            ],
        ];

        $helper = new HelperForm();
        $helper->module = $this;
        $helper->name_controller = $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex . '&configure=' . $this->name;
        $helper->submit_action = 'submitAlyaPayConfig';
        $helper->default_form_language = (int) Configuration::get('PS_LANG_DEFAULT');

        $configFields = $this->getConfigFields();
        foreach ($configFields as $key => $meta) {
            $helper->fields_value[$key] = Configuration::get($key);
        }

        return $helper->generateForm($fields_form);
    }

    // ─── Payment hook ──────────────────────────────────────────────────

    public function hookPaymentOptions($params)
    {
        if (!$this->active) {
            return [];
        }

        $config = new AlyaPayConfig();
        if (!$config->isActive()) {
            return [];
        }

        $cart = $params['cart'];
        $total = (float) $cart->getOrderTotal();
        if (!$config->isAmountInRange($total)) {
            return [];
        }

        $option = new \PrestaShop\PrestaShop\Core\Payment\PaymentOption();
        $option->setModuleName($this->name)
            ->setCallToActionText(Configuration::get('ALYAPAY_TITLE') ?: $this->l('Pay with AlyaPay'))
            ->setAction($this->context->link->getModuleLink($this->name, 'redirect', [], true));

        if ($config->isWidgetEnabled()) {
            $this->context->smarty->assign([
                'alyapay_price' => number_format($total, 2, '.', ''),
                'alyapay_currency' => $config->getWidgetCurrencyEffective(),
                'alyapay_lang' => $config->getWidgetLang(),
                'alyapay_theme' => $config->getWidgetTheme(),
                'alyapay_variant' => $config->getWidgetVariant(),
                'alyapay_detail' => $config->getWidgetDetail(),
                'alyapay_logo_position' => $config->getWidgetLogoPosition(),
                'alyapay_full_width' => $config->getWidgetFullWidth(),
                'alyapay_margin_x' => $config->getWidgetMarginX(),
                'alyapay_margin_y' => $config->getWidgetMarginY(),
                'alyapay_padding_x' => $config->getWidgetPaddingX(),
                'alyapay_padding_y' => $config->getWidgetPaddingY(),
            ]);
            $option->setAdditionalInformation(
                $this->context->smarty->fetch('module:alyapay/views/templates/front/payment_option.tpl')
            );
        }

        return [$option];
    }

    // ─── Payment return (success page) ─────────────────────────────────

    public function hookDisplayPaymentReturn($params)
    {
        if (!$this->active) {
            return '';
        }

        $order = isset($params['order']) ? $params['order'] : null;
        if (!$order || !Validate::isLoadedObject($order)) {
            return '';
        }

        if ($order->module !== $this->name) {
            return '';
        }

        $config = new AlyaPayConfig();
        $currencyCode = 'MAD';
        try {
            $currency = new Currency($order->id_currency);
            $currencyCode = $currency->iso_code ?: 'MAD';
        } catch (\Throwable $e) {
        }

        $installments = [];
        $installmentCount = 4;
        $installmentAmt = number_format((float) $order->total_paid / $installmentCount, 2, ',', ' ') . ' ' . $currencyCode;

        $payments = OrderPayment::getByOrderReference($order->reference);
        $transactionId = null;
        if (!empty($payments)) {
            foreach ($payments as $payment) {
                if (!empty($payment->transaction_id)) {
                    $transactionId = $payment->transaction_id;
                    break;
                }
            }
        }

        if ($transactionId) {
            try {
                $client = new AlyaPayApiClient($config);
                $scheduleService = new AlyaPayScheduleService($client);
                $schedules = $scheduleService->getSchedules($transactionId);

                if (!empty($schedules['installments'])) {
                    $raw = $schedules['installments'];
                    usort($raw, function ($a, $b) {
                        $da = $a['dueDate'] ?? '';
                        $db = $b['dueDate'] ?? '';
                        if (!$da || !$db) {
                            return 0;
                        }
                        return strcmp($da, $db);
                    });

                    $installmentCount = count($raw);
                    foreach ($raw as $idx => $row) {
                        $status = strtoupper($row['status'] ?? 'SCHEDULED');
                        $statusKey = 'scheduled';
                        if ($status === 'PAID' || $status === 'COMPLETED') {
                            $statusKey = 'paid';
                        } elseif (in_array($status, ['PENDING', 'UPCOMING', 'DUE'])) {
                            $statusKey = 'upcoming';
                        }

                        $label = '—';
                        if (!empty($row['dueDate'])) {
                            try {
                                $dt = new \DateTimeImmutable($row['dueDate']);
                                $label = $dt->format('M j, Y');
                            } catch (\Throwable $e) {
                                $label = $row['dueDate'];
                            }
                        }

                        $amount = number_format((float) ($row['amount'] ?? 0), 2, ',', ' ') . ' ' . $currencyCode;
                        $installments[] = [
                            'label' => $label,
                            'date' => '',
                            'amount' => $amount,
                            'status' => $statusKey,
                        ];
                    }
                    if (!empty($installments)) {
                        $installmentAmt = $installments[0]['amount'];
                    }
                }
            } catch (\Throwable $e) {
                PrestaShopLogger::addLog(
                    'AlyaPay schedules API failed: ' . $e->getMessage(),
                    2,
                    null,
                    'AlyaPay'
                );
            }
        }

        $lang = $config->getWidgetLang();
        $dir = ($lang === 'ar') ? 'rtl' : 'ltr';

        $t = $this->getSuccessTranslations($lang, $installmentCount, $installmentAmt);

        $this->context->smarty->assign([
            'alyapay_order_id' => $order->reference,
            'alyapay_order_date' => date('d/m/Y', strtotime($order->date_add)),
            'alyapay_order_total' => number_format((float) $order->total_paid, 2, ',', ' ') . ' ' . $currencyCode,
            'alyapay_installment_count' => $installmentCount,
            'alyapay_installment_amount' => $installmentAmt,
            'alyapay_installments' => $installments,
            'alyapay_dir' => $dir,
            'alyapay_t' => $t,
            'alyapay_view_order_url' => $this->context->link->getPageLink('order-detail', true, null, ['id_order' => $order->id]),
            'alyapay_continue_url' => $this->context->link->getPageLink('index'),
        ]);

        return $this->context->smarty->fetch('module:alyapay/views/templates/front/payment_return.tpl');
    }

    private function getSuccessTranslations(string $lang, int $installmentCount = 4, string $installmentAmt = ''): array
    {
        $map = [
            'fr' => [
                'title' => 'Commande confirmée',
                'sub' => 'Votre plan en ' . $installmentCount . ' versements est actif. Nous gérons le reste.',
                'summary' => 'Récapitulatif de commande',
                'label_order' => 'Commande',
                'label_date' => 'Date',
                'label_method' => 'Mode de paiement',
                'label_total' => 'Total',
                'install_note' => $installmentCount . ' versements de ' . $installmentAmt . ' chacun',
                'schedule' => 'Échéancier de paiement',
                'auto_note' => 'Les versements seront prélevés automatiquement à chaque échéance.',
                'powered' => 'Sécurisé par <strong>AlyaPay</strong> · Achetez maintenant, payez plus tard',
                'btn_view' => 'Voir la commande',
                'btn_continue' => 'Continuer les achats',
                'status_paid' => 'Payé',
                'status_upcoming' => 'À venir',
                'status_scheduled' => 'Planifié',
            ],
            'ar' => [
                'title' => 'تم تأكيد الطلب',
                'sub' => 'خطة التقسيط على ' . $installmentCount . ' أقساط نشطة. سنتولى الباقي.',
                'summary' => 'ملخص الطلب',
                'label_order' => 'الطلب',
                'label_date' => 'التاريخ',
                'label_method' => 'طريقة الدفع',
                'label_total' => 'المجموع',
                'install_note' => $installmentCount . ' أقساط بقيمة ' . $installmentAmt . ' لكل منها',
                'schedule' => 'جدول الدفع',
                'auto_note' => 'سيتم خصم الأقساط تلقائياً في كل تاريخ استحقاق.',
                'powered' => 'مدعوم من <strong>AlyaPay</strong> · اشترِ الآن وادفع لاحقاً',
                'btn_view' => 'عرض الطلب',
                'btn_continue' => 'مواصلة التسوق',
                'status_paid' => 'مدفوع',
                'status_upcoming' => 'قادم',
                'status_scheduled' => 'مجدول',
            ],
        ];

        $default = [
            'title' => 'Order confirmed',
            'sub' => 'Your ' . $installmentCount . '-installment plan is active. We\'ll handle the rest.',
            'summary' => 'Order summary',
            'label_order' => 'Order',
            'label_date' => 'Date',
            'label_method' => 'Payment method',
            'label_total' => 'Total',
            'install_note' => $installmentCount . ' installments of ' . $installmentAmt . ' each',
            'schedule' => 'Payment schedule',
            'auto_note' => 'Installments will be deducted automatically on each due date.',
            'powered' => 'Secured by <strong>AlyaPay</strong> · Buy Now, Pay Later',
            'btn_view' => 'View order',
            'btn_continue' => 'Continue shopping',
            'status_paid' => 'Paid',
            'status_upcoming' => 'Upcoming',
            'status_scheduled' => 'Scheduled',
        ];

        return $map[$lang] ?? $default;
    }

    // ─── Product promo widget ──────────────────────────────────────────

    public function hookDisplayProductAdditionalInfo($params)
    {
        if (!$this->active) {
            return '';
        }

        $config = new AlyaPayConfig();
        if (!$config->isActive() || !$config->isCreditPromoProductEnabled()) {
            return '';
        }

        $product = $params['product'] ?? null;
        if (!$product) {
            return '';
        }

        $price = (float) ($product->price_amount ?? $product->price ?? 0);
        if ($price <= 0 || !$config->isAmountInRange($price)) {
            return '';
        }

        $this->context->smarty->assign([
            'alyapay_price' => number_format($price, 2, '.', ''),
            'alyapay_currency' => $config->getWidgetCurrencyEffective(),
            'alyapay_lang' => $config->getWidgetLang(),
            'alyapay_theme' => $config->getProductWidgetTheme(),
            'alyapay_variant' => $config->getProductWidgetVariant(),
            'alyapay_detail' => $config->getProductWidgetDetail(),
            'alyapay_logo_position' => $config->getProductWidgetLogoPosition(),
            'alyapay_full_width' => $config->getProductWidgetFullWidth(),
            'alyapay_margin_x' => $config->getProductWidgetMarginX(),
            'alyapay_margin_y' => $config->getProductWidgetMarginY(),
            'alyapay_padding_x' => $config->getProductWidgetPaddingX(),
            'alyapay_padding_y' => $config->getProductWidgetPaddingY(),
        ]);

        return $this->context->smarty->fetch('module:alyapay/views/templates/front/product_promo.tpl');
    }

    // ─── Cart promo widget ─────────────────────────────────────────────

    public function hookDisplayShoppingCartFooter($params)
    {
        if (!$this->active) {
            return '';
        }

        $config = new AlyaPayConfig();
        if (!$config->isActive() || !$config->isCreditPromoCartEnabled()) {
            return '';
        }

        $cart = $this->context->cart;
        if (!$cart || $cart->nbProducts() === 0) {
            return '';
        }

        $total = (float) $cart->getOrderTotal();
        if (!$config->isAmountInRange($total)) {
            return '';
        }

        $this->context->smarty->assign([
            'alyapay_price' => number_format($total, 2, '.', ''),
            'alyapay_currency' => $config->getWidgetCurrencyEffective(),
            'alyapay_lang' => $config->getWidgetLang(),
            'alyapay_theme' => $config->getCartWidgetTheme(),
            'alyapay_variant' => $config->getCartWidgetVariant(),
            'alyapay_detail' => $config->getCartWidgetDetail(),
            'alyapay_logo_position' => $config->getCartWidgetLogoPosition(),
            'alyapay_full_width' => $config->getCartWidgetFullWidth(),
            'alyapay_margin_x' => $config->getCartWidgetMarginX(),
            'alyapay_margin_y' => $config->getCartWidgetMarginY(),
            'alyapay_padding_x' => $config->getCartWidgetPaddingX(),
            'alyapay_padding_y' => $config->getCartWidgetPaddingY(),
        ]);

        return $this->context->smarty->fetch('module:alyapay/views/templates/front/cart_promo.tpl');
    }

    // ─── Frontend media ────────────────────────────────────────────────

    public function hookActionFrontControllerSetMedia()
    {
        $config = new AlyaPayConfig();
        if (!$config->isActive()) {
            return;
        }

        $anyWidget = $config->isWidgetEnabled()
            || $config->isCreditPromoProductEnabled()
            || $config->isCreditPromoCartEnabled();

        if (!$anyWidget) {
            return;
        }

        $this->context->controller->registerJavascript(
            'alyapay-placement-cdn',
            'https://cdn.alyapay.com/js/alya-placement.js',
            ['server' => 'remote', 'position' => 'head', 'priority' => 150, 'attributes' => 'defer']
        );

        $this->context->controller->registerJavascript(
            'alyapay-placement-local',
            'modules/' . $this->name . '/views/js/alyapay-placement.js',
            ['position' => 'bottom', 'priority' => 200]
        );

        $this->applyCspWhitelist();
    }

    private function applyCspWhitelist(): void
    {
        if (headers_sent()) {
            return;
        }

        $sources = require __DIR__ . '/csp_whitelist.php';

        $hasCsp = false;
        foreach (headers_list() as $header) {
            if (stripos($header, 'Content-Security-Policy') !== false) {
                $hasCsp = true;
                break;
            }
        }

        if ($hasCsp) {
            PrestaShopLogger::addLog(
                'AlyaPay: existing CSP header detected. Manually add cdn.alyapay.com to script-src and img-src. See modules/alyapay/csp_whitelist.php.',
                1,
                null,
                'AlyaPay'
            );
            return;
        }

        $scriptSrc = implode(' ', $sources['script-src']);
        $imgSrc    = implode(' ', $sources['img-src']);
        $connectSrc = implode(' ', $sources['connect-src']);

        header(
            "Content-Security-Policy: "
            . "script-src 'self' 'unsafe-inline' 'unsafe-eval' " . $scriptSrc . "; "
            . "img-src 'self' data: blob: " . $imgSrc . "; "
            . "connect-src 'self' " . $connectSrc . ";",
            false
        );
    }

    // ─── Admin media (optional CSS/JS in BO) ───────────────────────────

    public function hookActionAdminControllerSetMedia()
    {
        // Reserved for future admin assets
    }
}
