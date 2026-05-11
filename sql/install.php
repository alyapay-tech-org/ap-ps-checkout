<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

$orderStates = [
    'ALYAPAY_PENDING' => [
        'color' => '#4169E1',
        'name' => 'Awaiting AlyaPay payment',
        'send_email' => false,
        'module_name' => 'alyapay',
        'invoice' => false,
        'logable' => false,
        'paid' => false,
    ],
];

// Register module for all active currencies across all shops
$id_module = (int) Module::getModuleIdByName('alyapay');
if ($id_module > 0) {
    foreach (Shop::getShops(true, null, true) as $id_shop) {
        foreach (Currency::getCurrencies(false, true) as $currency) {
            $id_currency = (int) $currency['id_currency'];
            $exists = (int) Db::getInstance()->getValue(
                'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'module_currency`
                WHERE `id_module` = ' . $id_module . '
                AND `id_shop` = ' . (int) $id_shop . '
                AND `id_currency` = ' . $id_currency
            );
            if (!$exists) {
                Db::getInstance()->insert('module_currency', [
                    'id_module'   => $id_module,
                    'id_shop'     => (int) $id_shop,
                    'id_currency' => $id_currency,
                ]);
            }
        }
    }
}

foreach ($orderStates as $configKey => $definition) {
    $existingId = (int) Configuration::get($configKey);
    if ($existingId > 0) {
        continue;
    }

    $os = new OrderState();
    $os->color = $definition['color'];
    $os->send_email = (bool) $definition['send_email'];
    $os->module_name = $definition['module_name'];
    $os->invoice = (bool) $definition['invoice'];
    $os->logable = (bool) $definition['logable'];
    $os->paid = (bool) $definition['paid'];
    $os->unremovable = true;

    foreach (Language::getLanguages(false) as $lang) {
        $os->name[$lang['id_lang']] = $definition['name'];
    }

    if ($os->add()) {
        Configuration::updateValue($configKey, (int) $os->id);
    }
}
