<?php

namespace App\Exceptions;

use Exception;
use Throwable;

/**
 * Типизированная ошибка ИИ-помощника.
 *
 * userMessage — безопасный текст, который можно показать пользователю;
 * httpStatus  — HTTP-статус ответа;
 * reason      — техническая причина для логов (без деталей провайдера).
 */
class AiChatException extends Exception
{
    private string $reason;

    public function __construct(
        string $userMessage,
        int $httpStatus = 502,
        string $reason = 'unknown',
        ?Throwable $previous = null,
    ) {
        parent::__construct($userMessage, $httpStatus, $previous);
        $this->reason = $reason;
    }

    /** Помощник выключен или не задан ключ. */
    public static function disabled(): self
    {
        return new self('ИИ-помощник временно недоступен.', 503, 'disabled');
    }

    /** Провайдер недоступен, вернул ошибку или пустой ответ. */
    public static function unavailable(string $reason = 'provider_unavailable'): self
    {
        return new self('Помощник временно недоступен. Попробуйте позже.', 502, $reason);
    }

    /** Исчерпана квота или лимит провайдера. */
    public static function quota(string $reason = 'provider_quota'): self
    {
        return new self('Лимит запросов помощника исчерпан. Попробуйте позже.', 503, $reason);
    }

    /** Провайдер не ответил вовремя. */
    public static function timeout(string $reason = 'provider_timeout'): self
    {
        return new self('Помощник не ответил вовремя. Попробуйте ещё раз.', 504, $reason);
    }

    public function userMessage(): string
    {
        return $this->getMessage();
    }

    public function httpStatus(): int
    {
        return $this->getCode() > 0 ? (int) $this->getCode() : 502;
    }

    public function reason(): string
    {
        return $this->reason;
    }
}
