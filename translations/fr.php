<?php

global $_MODULE;
$_MODULE = [];

// Module meta
$_MODULE['<{alyapay}prestashop>alyapay_9a2ccd41e01a5148495100c22d tried'] = 'AlyaPay';
$_MODULE['<{alyapay}prestashop>alyapay_description'] = 'Payez en plusieurs fois avec AlyaPay.';
$_MODULE['<{alyapay}prestashop>alyapay_uninstall'] = 'Êtes-vous sûr de vouloir désinstaller AlyaPay ?';

// Admin form
$_MODULE['<{alyapay}prestashop>alyapay_enabled'] = 'Activé';
$_MODULE['<{alyapay}prestashop>alyapay_title'] = 'Titre';
$_MODULE['<{alyapay}prestashop>alyapay_debug'] = 'Mode débogage';
$_MODULE['<{alyapay}prestashop>alyapay_save'] = 'Enregistrer';
$_MODULE['<{alyapay}prestashop>alyapay_saved'] = 'Paramètres enregistrés.';
$_MODULE['<{alyapay}prestashop>alyapay_pay'] = 'Payer avec AlyaPay';

// Error messages
$_MODULE['<{alyapay}prestashop>alyapay_invalid_api_key'] = 'Clé API invalide. Veuillez vérifier votre configuration AlyaPay.';
$_MODULE['<{alyapay}prestashop>alyapay_invalid_order_data'] = 'Données de commande invalides. Veuillez vérifier votre intégration.';
$_MODULE['<{alyapay}prestashop>alyapay_amount_too_low'] = 'Le montant de la commande est inférieur au minimum requis pour AlyaPay.';
$_MODULE['<{alyapay}prestashop>alyapay_amount_too_high'] = 'Le montant de la commande dépasse le maximum autorisé pour AlyaPay.';
$_MODULE['<{alyapay}prestashop>alyapay_validation_failed'] = 'Échec de la validation : %s';
$_MODULE['<{alyapay}prestashop>alyapay_invalid_request'] = 'Requête invalide. Veuillez vérifier votre configuration.';
$_MODULE['<{alyapay}prestashop>alyapay_payment_unavailable'] = 'Le paiement est temporairement indisponible. Veuillez réessayer plus tard.';
$_MODULE['<{alyapay}prestashop>alyapay_transaction_not_found'] = 'Transaction introuvable.';
$_MODULE['<{alyapay}prestashop>alyapay_status_check_failed'] = 'Impossible de vérifier le statut du paiement. Veuillez contacter le support.';
$_MODULE['<{alyapay}prestashop>alyapay_config_sync_failed'] = 'Impossible de synchroniser la configuration avec AlyaPay. Veuillez réessayer plus tard.';
$_MODULE['<{alyapay}prestashop>alyapay_generic_error'] = 'Une erreur de paiement s\'est produite. Veuillez réessayer ou contacter le support.';

// Controllers
$_MODULE['<{alyapay}prestashop>redirect_empty_cart'] = 'Votre panier est vide.';
$_MODULE['<{alyapay}prestashop>redirect_not_available'] = 'AlyaPay n\'est pas disponible.';
$_MODULE['<{alyapay}prestashop>success_invalid_return'] = 'Retour de paiement invalide.';
$_MODULE['<{alyapay}prestashop>success_order_not_found'] = 'Commande introuvable.';
$_MODULE['<{alyapay}prestashop>success_payment_failed'] = 'Le paiement a échoué. Veuillez réessayer ou choisir un autre moyen de paiement.';
$_MODULE['<{alyapay}prestashop>cancel_cancelled'] = 'Le paiement a été annulé.';
