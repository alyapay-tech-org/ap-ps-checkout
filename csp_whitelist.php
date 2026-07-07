<?php
/**
 * AlyaPay CSP Whitelist — PS8 equivalent of Magento's csp_whitelist.xml
 *
 * PrestaShop 8 does not ship with frontend CSP headers by default, and this
 * module NEVER sends a Content-Security-Policy header of its own (doing so
 * would block other third-party resources on shops without a CSP).
 *
 * This file is a reference for merchants who configure CSP at the server
 * level. If your shop sends a CSP header, the AlyaPay widget will be blocked
 * unless you allow cdn.alyapay.com.
 *    Add these to your Apache / Nginx / CDN CSP header:
 *
 *    script-src  https://cdn.alyapay.com
 *    img-src     https://cdn.alyapay.com
 *    connect-src https://cdn.alyapay.com
 *
 *    Apache (.htaccess) example — append to your existing Header directive:
 *      Header always set Content-Security-Policy \
 *        "... script-src 'self' https://cdn.alyapay.com; img-src 'self' https://cdn.alyapay.com;"
 *
 *    Nginx example:
 *      add_header Content-Security-Policy
 *        "... script-src 'self' https://cdn.alyapay.com; img-src 'self' https://cdn.alyapay.com;";
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

return [
    'script-src' => [
        'https://cdn.alyapay.com',
    ],
    'img-src' => [
        'https://cdn.alyapay.com',
    ],
    'connect-src' => [
        'https://cdn.alyapay.com',
    ],
];
