<?php
/**
 * AlyaPay CSP Whitelist — PS8 equivalent of Magento's csp_whitelist.xml
 *
 * PrestaShop 8 does not ship with frontend CSP headers by default, so this
 * file serves two purposes:
 *
 * 1. Machine-readable declaration of what cdn.alyapay.com needs (used by the
 *    hookActionFrontControllerSetMedia implementation in alyapay.php).
 *
 * 2. Reference for merchants who configure CSP at the server level.
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
