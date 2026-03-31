<?php

namespace App\Ai\Core;

use App\Ai\Agents\SmartAnonymousAgent;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class Reranker
{
    public function rerank(string $query, Collection $chunks): Collection
    {
        if ($chunks->isEmpty()) {
            return $chunks;
        }

        $text = $chunks->pluck('content')->implode("\n\n---\n\n");

        $prompt = "
Ты выбираешь наиболее релевантные куски текста.

Запрос:
{$query}

Тексты:
{$text}

Верни список ID самых релевантных (макс 3).
";

        $agent = new SmartAnonymousAgent($prompt);

        try {
            Log::info("Reranker: Переранжирование для запроса", ['query' => $query, 'chunks_count' => $chunks->count()]);
            $response = (string)$agent->prompt($query);
            // Упрощенно — возвращаем первые 3
            // В идеале мы должны парсить IDs, но в ТЗ сказано "упрощенно — оставим top chunks"
            return $chunks->take(3);
        } catch (\Exception $e) {
            Log::error("Reranker: Ошибка при переранжировании", ['error' => $e->getMessage()]);
            return $chunks->take(3);
        }
    }
}
