<?php

declare(strict_types=1);

namespace App\Infrastructure\Ai\Reranker;

use App\Domain\Ai\Knowledge\DocumentChunk;
use App\Domain\Ai\Knowledge\RerankerInterface;
use App\Infrastructure\Ai\Agent\SmartAnonymousAgent;
use Illuminate\Support\Facades\Log;

final class LlmReranker implements RerankerInterface
{
    public const int MAX_RESULTS = 5;

    public function rerank(string $query, array $chunks): array
    {
        if ($chunks === []) {
            return $chunks;
        }

        $text = implode(
            "\n\n---\n\n",
            array_map(
                static fn (DocumentChunk $c) => "ID: {$c->id}\nТекст: {$c->content}",
                $chunks,
            ),
        );

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
            Log::info('Reranker: reranking', ['query' => $query, 'chunks_count' => count($chunks)]);

            $response = (string) $agent->prompt($query);

            preg_match_all('/\d+/', $response, $matches);
            $foundIds = $matches[0] ?? [];

            if ($foundIds === []) {
                Log::warning('Reranker: failed to parse IDs', ['response' => $response]);
                return array_slice($chunks, 0, self::MAX_RESULTS);
            }

            $byId = [];
            foreach ($chunks as $chunk) {
                $byId[(string) $chunk->id] = $chunk;
            }

            $ordered = [];
            foreach ($foundIds as $id) {
                if (isset($byId[(string) $id])) {
                    $ordered[] = $byId[(string) $id];
                }
                if (count($ordered) >= self::MAX_RESULTS) {
                    break;
                }
            }

            return $ordered;
        } catch (\Throwable $e) {
            Log::error('Reranker: error', ['error' => $e->getMessage()]);
            return array_slice($chunks, 0, self::MAX_RESULTS);
        }
    }
}
