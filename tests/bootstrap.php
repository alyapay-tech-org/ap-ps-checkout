<?php

require_once __DIR__ . '/../vendor/autoload.php';

// The module's classes guard against direct access with `if (!defined('_PS_VERSION_')) exit;`.
// Define it here so the classmap autoloader can load them outside of a running PrestaShop instance.
if (!defined('_PS_VERSION_')) {
    define('_PS_VERSION_', 'test');
}
