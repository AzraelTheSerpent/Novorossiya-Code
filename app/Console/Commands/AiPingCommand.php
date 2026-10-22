<?php

namespace App\Console\Commands;

use App\Exceptions\AiChatException;
use App\Services\AiChatService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Smoke-тест провайдера без запуска веб-сервера.
 *
 * Ключ никогда не печатается — только факт его наличия.
 */
class AiPingCommand extends Command
{
    protected $signature = 'ai:ping {--message=Куда сходить с ребёнком?}';

    protected $description = 'Проверяет доступность ИИ-помощника (LiteRouter) и печатает короткий ответ';

    public function handle(AiChatService $service): int
    {
        $key = (string) config('services.literouter.key');

        $this->line('Base URL: '.rtrim((string) config('services.literouter.base_url'), '/'));
        $this->line('Модель:   '.(string) config('services.literouter.model'));
        $this->line('Ключ:     '.($key === '' ? 'НЕ ЗАДАН' : 'задан (значение скрыто)'));

        if (! config('services.literouter.enabled') || $key === '') {
            $this->error('ИИ-помощник выключен или ключ не задан.');

            return self::FAILURE;
        }

        $message = trim((string) $this->option('message'));

        if ($message === '') {
            $message = 'Куда сходить с ребёнком?';
        }

        $started = microtime(true);

        try {
            $result = $service->reply([['role' => 'user', 'content' => $message]]);
        } catch (AiChatException $e) {
            $this->error('Провайдер вернул ошибку (HTTP '.$e->httpStatus().'): '.$e->userMessage());
            $this->line('Причина: '.$e->reason());

            return self::FAILURE;
        }

        $this->line('HTTP:     200');
        $this->line('Время:    '.(int) round((microtime(true) - $started) * 1000).' мс');
        $this->line('Ответ:    '.Str::limit($result['reply'], 300, '…'));

        if ($result['usage']) {
            $this->line('Usage:    '.json_encode($result['usage'], JSON_UNESCAPED_UNICODE));
        }

        return self::SUCCESS;
    }
}
