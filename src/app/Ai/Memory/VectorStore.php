<?php

namespace App\Ai\Memory;

use App\Models\Document;
use Laravel\Ai\Ai;
use Pgvector\Laravel\Vector;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

use Pgvector\Laravel\Distance;

/**
 * A class for storing documents along with their embeddings and searching for similar documents.
 */
class VectorStore
{
    public function add(string $content, array $metadata = [], ?string $documentId = null): Collection
    {
        $documentId = $documentId ?? (string) Str::uuid();
        $title = $this->resolveTitle($content, $metadata);

        $finalChunks = $this->prepareChunks($content, $title);

        // Increase timeout for large batches to avoid Ollama cURL 28 timeouts
        $response = Ai::embeddings($finalChunks, null, null, 300);
        $embeddings = $response->embeddings;

        return collect($finalChunks)->map(function ($chunk, $index) use ($documentId, $metadata, $embeddings, $response) {
            return Document::create([
                'content' => $chunk,
                'metadata' => array_merge($metadata, [
                    'document_id' => $documentId,
                    'chunk_index' => $index,
                ]),
                'embedding' => new Vector(
                    $this->normalize($embeddings[$index] ?? $response->first())
                ),
            ]);
        });
    }

    /**
     * Resolve the title from metadata or content.
     */
    private function resolveTitle(string $content, array $metadata): string
    {
        $title = $metadata['title'] ?? '';

        if (empty($title) || in_array($title, ['Документ', 'Без названия'], true)) {
            $extracted = $this->extractTitle($content);
            if ($extracted !== 'Без названия') {
                return $extracted;
            }
        }

        return $title ?: 'Без названия';
    }

    /**
     * Split content into chunks with title prefix.
     */
    private function prepareChunks(string $content, string $title): array
    {
        $maxChunkSize = config('ai.vector_store.max_chunk_size', 1000);
        $overlapSize = config('ai.vector_store.overlap_size', 200);

        $paragraphs = preg_split("/\n\s*\n/", $content);
        $chunks = ["Документ: {$title}"];

        foreach ($paragraphs as $paragraph) {
            $paragraph = trim($paragraph);
            if (empty($paragraph) || mb_strlen($paragraph) < 50) {
                continue;
            }

            if (mb_strlen($paragraph) <= $maxChunkSize) {
                $chunks[] = "Документ: {$title}\n\n" . $paragraph;
            } else {
                $chunks = array_merge($chunks, $this->splitLongParagraph($paragraph, $title, $maxChunkSize, $overlapSize));
            }
        }

        return $chunks;
    }

    /**
     * Split a long paragraph into chunks with overlap.
     */
    private function splitLongParagraph(string $paragraph, string $title, int $maxSize, int $overlap): array
    {
        $chunks = [];
        $start = 0;
        $length = mb_strlen($paragraph);

        while ($start < $length) {
            $chunk = mb_substr($paragraph, $start, $maxSize);
            $chunks[] = "Документ: {$title}\n\n" . $chunk;
            $start += ($maxSize - $overlap);

            if ($length - $start < $overlap) {
                break;
            }
        }

        return $chunks;
    }

    /**
     * Search for similar documents with deduplication and noise filtering.
     */
    public function search(string $query, int $limit = 5): Collection
    {
        $embedding = $this->normalize(
            Ai::embeddings([$query], null, null, 300)->first()
        );

        $vector = new Vector($embedding);

        $results = Document::query()
            ->select('*')
            ->selectRaw("embedding <=> ? as distance", [$vector])
            ->orderByRaw("embedding <=> ?", [$vector]) // важно!
            ->limit(50)
            ->get();

        // ✅ 1. фильтр шума
        $results = $results->filter(fn ($r) => $r->distance < 0.65);

        // ✅ 2. dedup
        $results = $results->unique('content');

        // ✅ 3. сортировка
        $results = $results->sortBy('distance');

        return $results->take($limit);
    }

    private function extractTitle(string $content): string
    {
        $lines = preg_split('/\n+/', $content);

        foreach ($lines as $line) {
            $line = trim($line);

            if (
                mb_strlen($line) > 10 &&
                mb_strlen($line) < 120 &&
                !str_ends_with($line, '.')
            ) {
                return $line;
            }
        }

        return 'Без названия';
    }

    private function normalize(array $vector): array
    {
        $norm = sqrt(array_sum(array_map(fn($v) => $v * $v, $vector)));

        if ($norm == 0) return $vector;

        return array_map(fn($v) => $v / $norm, $vector);
    }
}
