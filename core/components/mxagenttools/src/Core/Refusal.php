<?php

namespace MxAgentTools\Core;

use RuntimeException;

/**
 * Отказ для человека, а не сбой: нет объекта, запрещённая привязка, план устарел. Эндпоинт отдаёт его ошибкой
 * mxApi с кодом code() (refusal | stale_plan) и текстом как есть; details — поля ошибки (current_hash, diff).
 */
class Refusal extends RuntimeException
{
    const REFUSAL = 'refusal';
    const STALE_PLAN = 'stale_plan';

    /** @var array */
    private $details;
    /** @var string */
    private $errorCode;

    public function __construct(string $message, array $details = [], string $code = self::REFUSAL)
    {
        parent::__construct($message);
        $this->details = $details;
        $this->errorCode = $code;
    }

    public static function stale(string $message, array $details): self
    {
        return new self($message, $details, self::STALE_PLAN);
    }

    public function details(): array
    {
        return $this->details;
    }

    public function code(): string
    {
        return $this->errorCode;
    }
}
