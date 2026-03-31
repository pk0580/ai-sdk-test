<?php

namespace App\Ai\Core;

use App\Ai\Agents\SmartAnonymousAgent;
use Illuminate\Support\Facades\Log;

class QueryRewriter
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
            $rewritten = trim((string)$agent->prompt($query));
            Log::info("QueryRewriter: Запрос переписан", ['original' => $query, 'rewritten' => $rewritten]);
            return $rewritten;
        } catch (\Exception $e) {
            Log::error("QueryRewriter: Ошибка при переписывании запроса", ['error' => $e->getMessage()]);
            return $query;
        }
    }
}
