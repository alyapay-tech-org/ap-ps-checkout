<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Defers the order_conf email until AlyaPay approves the payment.
 *
 * PaymentModule::validateOrder() sends order_conf immediately when the
 * (pending) order is created, before the customer even reaches AlyaPay.
 * hookActionEmailSendBefore captures the fully-built email here instead;
 * it is released by AlyaPayOrderHelper::approveOrder() once payment is
 * confirmed, or discarded when the payment is cancelled or expires.
 */
class AlyaPayEmailQueue
{
    private const TABLE = 'alyapay_email_queue';
    private const RETENTION_DAYS = 7;

    /**
     * Stores the email instead of sending it. Returns false when the row
     * could not be stored — in that case the caller must NOT block the
     * email, otherwise it would be lost entirely.
     */
    public static function capture(Order $order, array $params): bool
    {
        try {
            // Opportunistic cleanup of abandoned pending orders.
            Db::getInstance()->execute(
                'DELETE FROM `' . _DB_PREFIX_ . self::TABLE . '`
                 WHERE `date_add` < DATE_SUB(NOW(), INTERVAL ' . (int) self::RETENTION_DAYS . ' DAY)'
            );

            $recipient = [
                'to' => $params['to'] ?? null,
                'toName' => $params['toName'] ?? null,
            ];

            return Db::getInstance()->execute(
                'INSERT INTO `' . _DB_PREFIX_ . self::TABLE . '`
                 (`order_reference`, `id_lang`, `id_shop`, `subject`, `template_vars`, `recipient`, `date_add`)
                 VALUES (
                     \'' . pSQL($order->reference) . '\',
                     ' . (int) ($params['idLang'] ?? $order->id_lang) . ',
                     ' . (int) ($params['idShop'] ?? $order->id_shop) . ',
                     \'' . pSQL((string) ($params['subject'] ?? ''), true) . '\',
                     \'' . pSQL((string) json_encode($params['templateVars'] ?? []), true) . '\',
                     \'' . pSQL((string) json_encode($recipient), true) . '\',
                     NOW()
                 )'
            );
        } catch (\Throwable $e) {
            PrestaShopLogger::addLog(
                'AlyaPay: could not queue order_conf for ' . $order->reference . ': ' . $e->getMessage(),
                3,
                null,
                'AlyaPay'
            );
            return false;
        }
    }

    /**
     * Sends the queued order_conf email(s) for this order, then removes them.
     */
    public static function flush(Order $order): void
    {
        try {
            $rows = Db::getInstance()->executeS(
                'SELECT * FROM `' . _DB_PREFIX_ . self::TABLE . '`
                 WHERE `order_reference` = \'' . pSQL($order->reference) . '\''
            );
        } catch (\Throwable $e) {
            return;
        }

        if (empty($rows)) {
            return;
        }

        foreach ($rows as $row) {
            $templateVars = json_decode((string) $row['template_vars'], true) ?: [];
            $recipient = json_decode((string) $row['recipient'], true) ?: [];

            try {
                Mail::Send(
                    (int) $row['id_lang'],
                    'order_conf',
                    (string) $row['subject'],
                    $templateVars,
                    $recipient['to'] ?? null,
                    $recipient['toName'] ?? null,
                    null,
                    null,
                    null,
                    null,
                    _PS_MAIL_DIR_,
                    false,
                    (int) $row['id_shop']
                );
                PrestaShopLogger::addLog(
                    'AlyaPay: deferred order_conf sent for ' . $order->reference,
                    1,
                    null,
                    'AlyaPay'
                );
            } catch (\Throwable $e) {
                PrestaShopLogger::addLog(
                    'AlyaPay: deferred order_conf failed for ' . $order->reference . ': ' . $e->getMessage(),
                    3,
                    null,
                    'AlyaPay'
                );
            }
        }

        self::discard($order->reference);
    }

    /**
     * Drops queued email(s) without sending (cancelled / expired payments).
     */
    public static function discard(string $reference): void
    {
        try {
            Db::getInstance()->execute(
                'DELETE FROM `' . _DB_PREFIX_ . self::TABLE . '`
                 WHERE `order_reference` = \'' . pSQL($reference) . '\''
            );
        } catch (\Throwable $e) {
            // Table may not exist (e.g. during uninstall) — nothing to do.
        }
    }
}
