<?php

declare(strict_types=1);

namespace App\Infrastructure\Ai\QueryRewriter;

use App\Domain\Ai\Knowledge\QueryRewriterInterface;
use App\Infrastructure\Ai\Agent\SmartAnonymousAgent;
use Illuminate\Support\Facades\Log;

final class LlmQueryRewriter implements QueryRewriterInterface
{
    public function rewrite(string $query): string
    {
        $prompt = "Переформулируй запрос для поиска в документации.
        Убери лишние слова, оставь только ключевые термины.

        Пример:
        'Расскажи как работает авторизация'
        → 'авторизация алгоритм login auth API'

        Запрос: {$query}";

        $agent = new SmartAnonymousAgent($prompt);

        try {
            $rewritten = trim((string) $agent->prompt($query));
            Log::info('QueryRewriter: rewritten', ['original' => $query, 'rewritten' => $rewritten]);
            return $rewritten;
        } catch (\Throwable $e) {
            Log::error('QueryRewriter: rewrite failed', ['error' => $e->getMessage()]);
            return $query;
        }
    }
}
