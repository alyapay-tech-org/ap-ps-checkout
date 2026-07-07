<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

// Read before the config keys are deleted below.
$pendingStateId = (int) Configuration::get('ALYAPAY_PENDING');

$configKeys = [
    'ALYAPAY_ACTIVE',
    'ALYAPAY_TITLE',
    'ALYAPAY_API_KEY',
    'ALYAPAY_API_BASE_URL',
    'ALYAPAY_WEBHOOK_URL',
    'ALYAPAY_WEBHOOK_SECRET',
    'ALYAPAY_TRANSACTION_EXPIRY',
    'ALYAPAY_AMOUNT_MIN',
    'ALYAPAY_AMOUNT_MAX',
    'ALYAPAY_STATUS_APPROVED',
    'ALYAPAY_STATUS_CANCELED',
    'ALYAPAY_STATUS_EXPIRED',
    'ALYAPAY_DEBUG',
    'ALYAPAY_WIDGET_CHECKOUT_ENABLED',
    'ALYAPAY_WIDGET_PRODUCT_ENABLED',
    'ALYAPAY_WIDGET_CART_ENABLED',
    'ALYAPAY_WIDGET_THEME',
    'ALYAPAY_WIDGET_CURRENCY',
    'ALYAPAY_WIDGET_VARIANT',
    'ALYAPAY_WIDGET_DETAIL',
    'ALYAPAY_WIDGET_LOGO_POSITION',
    'ALYAPAY_PENDING',
];

foreach ($configKeys as $key) {
    Configuration::deleteByName($key);
}

// Remove the module's order state so reinstalling does not create duplicates.
if ($pendingStateId > 0) {
    $state = new OrderState($pendingStateId);
    if (Validate::isLoadedObject($state)) {
        $state->delete();
    }
}

Db::getInstance()->execute('DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'alyapay_email_queue`');
