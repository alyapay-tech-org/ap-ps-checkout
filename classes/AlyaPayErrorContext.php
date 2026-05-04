<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

class AlyaPayErrorContext
{
    public const PARTNER_CONFIG = 'partner_config';
    public const SESSION_INTENT = 'session_intent';
    public const WEBHOOK = 'webhook';
    public const STATUS_CHECK = 'status_check';
    public const REDIRECT = 'redirect';
}
