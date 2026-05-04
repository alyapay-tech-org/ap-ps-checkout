<?php

global $_MODULE;
$_MODULE = [];

// Module meta
$_MODULE['<{alyapay}prestashop>alyapay_9a2ccd41e01a5148495100c22d tried'] = 'AlyaPay';
$_MODULE['<{alyapay}prestashop>alyapay_description'] = 'Pay in installments with AlyaPay.';
$_MODULE['<{alyapay}prestashop>alyapay_uninstall'] = 'Are you sure you want to uninstall AlyaPay?';

// Admin form
$_MODULE['<{alyapay}prestashop>alyapay_enabled'] = 'Enabled';
$_MODULE['<{alyapay}prestashop>alyapay_title'] = 'Title';
$_MODULE['<{alyapay}prestashop>alyapay_debug'] = 'Debug Mode';
$_MODULE['<{alyapay}prestashop>alyapay_save'] = 'Save';
$_MODULE['<{alyapay}prestashop>alyapay_saved'] = 'Settings saved.';
$_MODULE['<{alyapay}prestashop>alyapay_pay'] = 'Pay with AlyaPay';

// Error messages
$_MODULE['<{alyapay}prestashop>alyapay_invalid_api_key'] = 'Invalid API key. Please check your AlyaPay configuration.';
$_MODULE['<{alyapay}prestashop>alyapay_invalid_order_data'] = 'Invalid order data. Please check your integration.';
$_MODULE['<{alyapay}prestashop>alyapay_amount_too_low'] = 'The order amount is below the minimum for AlyaPay.';
$_MODULE['<{alyapay}prestashop>alyapay_amount_too_high'] = 'The order amount exceeds the maximum for AlyaPay.';
$_MODULE['<{alyapay}prestashop>alyapay_validation_failed'] = 'Validation failed: %s';
$_MODULE['<{alyapay}prestashop>alyapay_invalid_request'] = 'Invalid request. Please check your configuration.';
$_MODULE['<{alyapay}prestashop>alyapay_payment_unavailable'] = 'Payment is temporarily unavailable. Please try again later.';
$_MODULE['<{alyapay}prestashop>alyapay_transaction_not_found'] = 'Transaction not found.';
$_MODULE['<{alyapay}prestashop>alyapay_status_check_failed'] = 'Unable to verify payment status. Please contact support.';
$_MODULE['<{alyapay}prestashop>alyapay_config_sync_failed'] = 'Unable to sync configuration to AlyaPay. Please try again later.';
$_MODULE['<{alyapay}prestashop>alyapay_generic_error'] = 'A payment error occurred. Please try again or contact support.';

// Controllers
$_MODULE['<{alyapay}prestashop>redirect_empty_cart'] = 'Your cart is empty.';
$_MODULE['<{alyapay}prestashop>redirect_not_available'] = 'AlyaPay is not available.';
$_MODULE['<{alyapay}prestashop>success_invalid_return'] = 'Invalid return from payment.';
$_MODULE['<{alyapay}prestashop>success_order_not_found'] = 'Order not found.';
$_MODULE['<{alyapay}prestashop>success_payment_failed'] = 'Payment failed. Please try again or choose another payment method.';
$_MODULE['<{alyapay}prestashop>cancel_cancelled'] = 'Payment was cancelled.';
