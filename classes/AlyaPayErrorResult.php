<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

class AlyaPayErrorResult
{
    public const SEVERITY_ERROR = 'error';
    public const SEVERITY_WARNING = 'warning';
    public const SEVERITY_NOTICE = 'notice';

    public const AUDIENCE_ADMIN = 'admin';
    public const AUDIENCE_CUSTOMER = 'customer';

    /** @var string */
    private $userMessage;

    /** @var string */
    private $logMessage;

    /** @var string */
    private $severity;

    /** @var string */
    private $audience;

    public function __construct(
        string $userMessage,
        string $logMessage,
        string $severity = self::SEVERITY_ERROR,
        string $audience = self::AUDIENCE_ADMIN
    ) {
        $this->userMessage = $userMessage;
        $this->logMessage = $logMessage;
        $this->severity = $severity;
        $this->audience = $audience;
    }

    public function getUserMessage(): string
    {
        return $this->userMessage;
    }

    public function getLogMessage(): string
    {
        return $this->logMessage;
    }

    public function getSeverity(): string
    {
        return $this->severity;
    }

    public function getAudience(): string
    {
        return $this->audience;
    }

    public function isForAdmin(): bool
    {
        return $this->audience === self::AUDIENCE_ADMIN;
    }

    public function isForCustomer(): bool
    {
        return $this->audience === self::AUDIENCE_CUSTOMER;
    }
}
