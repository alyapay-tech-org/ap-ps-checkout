<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

class AlyaPayConfig
{
    private const WEBHOOK_PATH = '/modules/alyapay/webhook.php';

    public function isActive(): bool
    {
        return (bool) Configuration::get('ALYAPAY_ACTIVE');
    }

    public function getApiKey(): ?string
    {
        $value = Configuration::get('ALYAPAY_API_KEY');
        return !empty($value) ? trim($value) : null;
    }

    public function getApiBaseUrl(): string
    {
        $url = Configuration::get('ALYAPAY_API_BASE_URL');
        return $url ? rtrim($url, '/') : 'https://sandbox-api.alyapay.com';
    }

    public function isDebugEnabled(): bool
    {
        return (bool) Configuration::get('ALYAPAY_DEBUG');
    }

    public function getWebhookUrl(): string
    {
        $value = Configuration::get('ALYAPAY_WEBHOOK_URL');
        if (!empty(trim((string) $value))) {
            $base = rtrim($value, '/');
            $pathLen = strlen(self::WEBHOOK_PATH);
            return (substr($base, -$pathLen) === self::WEBHOOK_PATH)
                ? $base
                : $base . self::WEBHOOK_PATH;
        }

        $shopUrl = Tools::getShopDomainSsl(true);
        if (!empty($shopUrl)) {
            return rtrim($shopUrl, '/') . self::WEBHOOK_PATH;
        }

        return '';
    }

    public function getWebhookSecret(): ?string
    {
        $value = Configuration::get('ALYAPAY_WEBHOOK_SECRET');
        return !empty($value) ? trim($value) : null;
    }

    public function getTransactionExpiry(): int
    {
        $value = Configuration::get('ALYAPAY_TRANSACTION_EXPIRY');
        if ($value === null || $value === '' || $value === false) {
            return 30;
        }
        return (int) $value;
    }

    public function getAmountMin(): float
    {
        $value = Configuration::get('ALYAPAY_AMOUNT_MIN');
        return ($value !== null && $value !== '' && $value !== false) ? (float) $value : 500.0;
    }

    public function getAmountMax(): float
    {
        $value = Configuration::get('ALYAPAY_AMOUNT_MAX');
        return ($value !== null && $value !== '' && $value !== false) ? (float) $value : 15000.0;
    }

    public function isAmountInRange(float $amount): bool
    {
        return $amount >= $this->getAmountMin() && $amount <= $this->getAmountMax();
    }

    public function getApprovedStatus(): int
    {
        $value = Configuration::get('ALYAPAY_STATUS_APPROVED');
        return $value ? (int) $value : (int) Configuration::get('PS_OS_PAYMENT');
    }

    public function getCanceledStatus(): int
    {
        $value = Configuration::get('ALYAPAY_STATUS_CANCELED');
        return $value ? (int) $value : (int) Configuration::get('PS_OS_CANCELED');
    }

    public function getExpiredStatus(): int
    {
        $value = Configuration::get('ALYAPAY_STATUS_EXPIRED');
        return $value ? (int) $value : (int) Configuration::get('PS_OS_CANCELED');
    }

    public function getPendingStatus(): int
    {
        $value = Configuration::get('ALYAPAY_PENDING');
        return $value ? (int) $value : (int) Configuration::get('PS_OS_CHEQUE');
    }

    // ─── Reconciliation cron ───────────────────────────────────────────

    private const RECONCILE_LOCK_NAME = 'alyapay_reconcile';

    /**
     * Acquires a MySQL named lock for a reconciliation run — atomic (unlike a
     * Configuration get-then-set flag, which two overlapping runs could both
     * pass) and auto-released if the connection drops, so a crashed run can't
     * leave the lock stuck.
     */
    public function acquireReconcileLock(): bool
    {
        $result = Db::getInstance()->getValue(
            'SELECT GET_LOCK(\'' . pSQL(self::RECONCILE_LOCK_NAME) . '\', 0)'
        );
        return (int) $result === 1;
    }

    public function releaseReconcileLock(): void
    {
        Db::getInstance()->execute(
            'SELECT RELEASE_LOCK(\'' . pSQL(self::RECONCILE_LOCK_NAME) . '\')'
        );
    }

    public function isShowDisabledBelowMin(): bool
    {
        return (bool) Configuration::get('ALYAPAY_SHOW_DISABLED_BELOW_MIN');
    }

    public function getDisabledTitle(): string
    {
        $value = Configuration::get('ALYAPAY_DISABLED_TITLE');
        return $value !== false ? (string) $value : '';
    }

    public function isAmountBelowMin(float $amount): bool
    {
        return $amount < $this->getAmountMin();
    }

    public function isAmountAboveMax(float $amount): bool
    {
        return $amount > $this->getAmountMax();
    }

    // ─── Checkout widget ───────────────────────────────────────────────

    public function isWidgetEnabled(): bool
    {
        return (bool) Configuration::get('ALYAPAY_WIDGET_CHECKOUT_ENABLED');
    }

    public function getWidgetTheme(): string
    {
        return (string) (Configuration::get('ALYAPAY_WIDGET_THEME') ?: 'light');
    }

    public function getWidgetVariant(): string
    {
        return (string) (Configuration::get('ALYAPAY_WIDGET_VARIANT') ?: 'default');
    }

    public function getWidgetDetail(): string
    {
        return (string) (Configuration::get('ALYAPAY_WIDGET_DETAIL') ?: 'modal');
    }

    public function getWidgetLogoPosition(): string
    {
        return (string) (Configuration::get('ALYAPAY_WIDGET_LOGO_POSITION') ?: 'right');
    }

    public function getWidgetFullWidth(): bool
    {
        return (bool) Configuration::get('ALYAPAY_WIDGET_FULL_WIDTH');
    }

    public function getWidgetMarginX(): string
    {
        $v = Configuration::get('ALYAPAY_WIDGET_MARGIN_X'); return $v !== false ? (string) $v : '';
    }

    public function getWidgetMarginY(): string
    {
        $v = Configuration::get('ALYAPAY_WIDGET_MARGIN_Y'); return $v !== false ? (string) $v : '';
    }

    public function getWidgetPaddingX(): string
    {
        $v = Configuration::get('ALYAPAY_WIDGET_PADDING_X'); return $v !== false ? (string) $v : '';
    }

    public function getWidgetPaddingY(): string
    {
        $v = Configuration::get('ALYAPAY_WIDGET_PADDING_Y'); return $v !== false ? (string) $v : '';
    }

    // ─── Product widget ────────────────────────────────────────────────

    public function isCreditPromoProductEnabled(): bool
    {
        return (bool) Configuration::get('ALYAPAY_WIDGET_PRODUCT_ENABLED');
    }

    public function getProductWidgetTheme(): string
    {
        return (string) (Configuration::get('ALYAPAY_PRODUCT_WIDGET_THEME') ?: 'light');
    }

    public function getProductWidgetVariant(): string
    {
        return (string) (Configuration::get('ALYAPAY_PRODUCT_WIDGET_VARIANT') ?: 'default');
    }

    public function getProductWidgetDetail(): string
    {
        return (string) (Configuration::get('ALYAPAY_PRODUCT_WIDGET_DETAIL') ?: 'modal');
    }

    public function getProductWidgetLogoPosition(): string
    {
        return (string) (Configuration::get('ALYAPAY_PRODUCT_WIDGET_LOGO_POSITION') ?: 'right');
    }

    public function getProductWidgetFullWidth(): bool
    {
        return (bool) Configuration::get('ALYAPAY_PRODUCT_WIDGET_FULL_WIDTH');
    }

    public function getProductWidgetMarginX(): string
    {
        $v = Configuration::get('ALYAPAY_PRODUCT_WIDGET_MARGIN_X'); return $v !== false ? (string) $v : '';
    }

    public function getProductWidgetMarginY(): string
    {
        $v = Configuration::get('ALYAPAY_PRODUCT_WIDGET_MARGIN_Y'); return $v !== false ? (string) $v : '';
    }

    public function getProductWidgetPaddingX(): string
    {
        $v = Configuration::get('ALYAPAY_PRODUCT_WIDGET_PADDING_X'); return $v !== false ? (string) $v : '';
    }

    public function getProductWidgetPaddingY(): string
    {
        $v = Configuration::get('ALYAPAY_PRODUCT_WIDGET_PADDING_Y'); return $v !== false ? (string) $v : '';
    }

    public function isProductWidgetShowBelowMin(): bool
    {
        return (bool) Configuration::get('ALYAPAY_PRODUCT_WIDGET_SHOW_BELOW_MIN');
    }

    // ─── Cart widget ───────────────────────────────────────────────────

    public function isCartWidgetShowBelowMin(): bool
    {
        return (bool) Configuration::get('ALYAPAY_CART_WIDGET_SHOW_BELOW_MIN');
    }

    public function getCartWidgetMinDisplay(): string
    {
        return (string) (Configuration::get('ALYAPAY_CART_WIDGET_MIN_DISPLAY') ?: 'rich');
    }

    public function isCreditPromoCartEnabled(): bool
    {
        return (bool) Configuration::get('ALYAPAY_WIDGET_CART_ENABLED');
    }

    public function getCartWidgetTheme(): string
    {
        return (string) (Configuration::get('ALYAPAY_CART_WIDGET_THEME') ?: 'light');
    }

    public function getCartWidgetVariant(): string
    {
        return (string) (Configuration::get('ALYAPAY_CART_WIDGET_VARIANT') ?: 'default');
    }

    public function getCartWidgetDetail(): string
    {
        return (string) (Configuration::get('ALYAPAY_CART_WIDGET_DETAIL') ?: 'modal');
    }

    public function getCartWidgetLogoPosition(): string
    {
        return (string) (Configuration::get('ALYAPAY_CART_WIDGET_LOGO_POSITION') ?: 'right');
    }

    public function getCartWidgetFullWidth(): bool
    {
        return (bool) Configuration::get('ALYAPAY_CART_WIDGET_FULL_WIDTH');
    }

    public function getCartWidgetMarginX(): string
    {
        $v = Configuration::get('ALYAPAY_CART_WIDGET_MARGIN_X'); return $v !== false ? (string) $v : '';
    }

    public function getCartWidgetMarginY(): string
    {
        $v = Configuration::get('ALYAPAY_CART_WIDGET_MARGIN_Y'); return $v !== false ? (string) $v : '';
    }

    public function getCartWidgetPaddingX(): string
    {
        $v = Configuration::get('ALYAPAY_CART_WIDGET_PADDING_X'); return $v !== false ? (string) $v : '';
    }

    public function getCartWidgetPaddingY(): string
    {
        $v = Configuration::get('ALYAPAY_CART_WIDGET_PADDING_Y'); return $v !== false ? (string) $v : '';
    }


    // ─── Shared ────────────────────────────────────────────────────────

    public function getWidgetCurrency(): ?string
    {
        $value = Configuration::get('ALYAPAY_WIDGET_CURRENCY');
        return !empty(trim((string) $value)) ? trim($value) : null;
    }

    public function getWidgetCurrencyEffective(): string
    {
        $configured = $this->getWidgetCurrency();
        return $configured !== null ? $configured : 'MAD';
    }

    public function getWidgetLang(): string
    {
        try {
            $context = Context::getContext();
            if ($context && $context->language) {
                $isoCode = $context->language->iso_code;
                if (in_array($isoCode, ['fr', 'en', 'ar'], true)) {
                    return $isoCode;
                }
            }
        } catch (\Throwable $e) {
        }
        return 'en';
    }
}
