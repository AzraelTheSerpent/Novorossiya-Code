<?php

namespace App\Http\Controllers;

use App\Exceptions\AiChatException;
use App\Services\AiChatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * Публичный эндпоинт ИИ-помощника.
 *
 * Клиент не может задать system-сообщение, модель, temperature или max_tokens —
 * всё это определяется на сервере.
 */
class AiChatController extends Controller
{
    public const MAX_MESSAGES = 12;
    public const MAX_TOTAL_CHARS = 8000;

    public function __construct(private readonly AiChatService $service) {}

    public function chat(Request $request): JsonResponse
    {
        $requestId = (string) Str::uuid();

        if (! config('services.literouter.enabled') || blank(config('services.literouter.key'))) {
            Log::info('ai.chat_failed', [
                'request_id' => $requestId,
                'reason' => 'disabled',
                'status' => 503,
            ]);

            return $this->error('ИИ-помощник временно недоступен.', 503, $requestId);
        }

        $validator = Validator::make($request->all(), [
            'messages' => ['required', 'array', 'min:1', 'max:'.self::MAX_MESSAGES],
            'messages.*.role' => ['required', 'string', 'in:user,assistant'],
            'messages.*.content' => ['required', 'string', 'min:1', 'max:2000'],
        ]);

        $validator->after(function ($validator) use ($request) {
            $messages = $request->input('messages');

            if (! is_array($messages) || $messages === []) {
                return;
            }

            $last = end($messages);

            if (is_array($last) && ($last['role'] ?? null) !== 'user') {
                $validator->errors()->add('messages', 'Последнее сообщение должно быть от пользователя.');
            }

            $total = 0;

            foreach ($messages as $message) {
                $total += mb_strlen(is_array($message) ? (string) ($message['content'] ?? '') : '');
            }

            if ($total > self::MAX_TOTAL_CHARS) {
                $validator->errors()->add('messages', 'Суммарная длина сообщений превышает допустимую.');
            }
        });

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Проверьте формат сообщений.',
                'errors' => $validator->errors(),
                'request_id' => $requestId,
            ], 422);
        }

        $messages = $validator->validated()['messages'];
        $started = microtime(true);

        try {
            $result = $this->service->reply($messages);
        } catch (AiChatException $e) {
            Log::warning('ai.chat_failed', [
                'request_id' => $requestId,
                'reason' => $e->reason(),
                'status' => $e->httpStatus(),
                'latency_ms' => $this->latency($started),
            ]);

            return $this->error($e->userMessage(), $e->httpStatus(), $requestId);
        }

        Log::info('ai.chat', [
            'request_id' => $requestId,
            'status' => 200,
            'model' => $result['model'],
            'latency_ms' => $this->latency($started),
            'prompt_tokens' => $result['usage']['prompt_tokens'] ?? null,
            'completion_tokens' => $result['usage']['completion_tokens'] ?? null,
            'messages' => count($messages),
        ]);

        return response()->json([
            'reply' => $result['reply'],
            'model' => $result['model'],
            'usage' => $result['usage'],
            'request_id' => $requestId,
        ]);
    }

    private function latency(float $started): int
    {
        return (int) round((microtime(true) - $started) * 1000);
    }

    private function error(string $message, int $status, string $requestId): JsonResponse
    {
        return response()->json([
            'message' => $message,
            'request_id' => $requestId,
        ], $status);
    }
}
