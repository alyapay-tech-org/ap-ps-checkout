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
