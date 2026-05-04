<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

class AlyaPayApiException extends Exception
{
    /** @var int */
    private $statusCode;

    /** @var string|null */
    private $key;

    /** @var int|null */
    private $apiCode;

    /** @var array */
    private $parameters;

    /** @var array */
    private $validationErrors;

    /** @var string */
    private $rawBody;

    public function __construct(
        string $message = '',
        int $statusCode = 0,
        ?string $key = null,
        ?int $apiCode = null,
        array $parameters = [],
        array $validationErrors = [],
        string $rawBody = ''
    ) {
        parent::__construct($message ?: "AlyaPay API error: HTTP $statusCode");
        $this->statusCode = $statusCode;
        $this->key = $key;
        $this->apiCode = $apiCode;
        $this->parameters = $parameters;
        $this->validationErrors = $validationErrors;
        $this->rawBody = $rawBody;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getKey(): ?string
    {
        return $this->key;
    }

    public function getApiCode(): ?int
    {
        return $this->apiCode;
    }

    public function getParameters(): array
    {
        return $this->parameters;
    }

    public function getValidationErrors(): array
    {
        return $this->validationErrors;
    }

    public function getRawBody(): string
    {
        return $this->rawBody;
    }
}
