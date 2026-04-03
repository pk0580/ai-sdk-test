<?php

namespace App\Ai\Core;

use App\Ai\Agents\SmartAnonymousAgent;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class Reranker
{
    const int MAX_RESULTS = 5;

    public function rerank(string $query, Collection $chunks): Collection
    {
        if ($chunks->isEmpty()) {
            return $chunks;
        }

        $text = $chunks->map(function ($chunk) {
            return "ID: {$chunk->id}\nТекст: {$chunk->content}";
        })->implode("\n\n---\n\n");

        $prompt = "
Ты — эксперт по поиску. Выбери наиболее релевантные куски текста для ответа на запрос.
Верни ТОЛЬКО список ID выбранных фрагментов через запятую (макс " . self::MAX_RESULTS . ". Не пиши никаких пояснений.

Запрос:
{$query}

Тексты:
{$text}
";

        $agent = new SmartAnonymousAgent($prompt);

        try {
            Log::info("Reranker: Переранжирование для запроса", ['query' => $query, 'chunks_count' => $chunks->count()]);
            $response = (string)$agent->prompt($query);

            // Парсим IDs из ответа (ищем числа через запятую или пробелы)
            preg_match_all('/\d+/', $response, $matches);
            $foundIds = $matches[0] ?? [];

            if (empty($foundIds)) {
                Log::warning("Reranker: Не удалось распарсить IDs из ответа", ['response' => $response]);
                return $chunks->take(self::MAX_RESULTS);
            }

            // Возвращаем чанки в порядке, указанном агентом
            return collect($foundIds)
                ->map(fn($id) => $chunks->firstWhere('id', $id))
                ->filter()
                ->take(self::MAX_RESULTS);

        } catch (\Exception $e) {
            Log::error("Reranker: Ошибка при переранжировании", ['error' => $e->getMessage()]);
            return $chunks->take(self::MAX_RESULTS);
        }
    }
}
