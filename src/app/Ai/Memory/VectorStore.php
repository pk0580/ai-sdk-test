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
    /**
     * Store a document, splitting it into chunks with metadata.
     */
    public function add(string $content, array $metadata = [], string $documentId = null): Collection
    {
        $documentId = $documentId ?? uniqid();
        $title = $metadata['title'] ?? $this->extractTitle($content);

        if (empty($title) || $title === 'Документ' || $title === 'Без названия') {
            $newTitle = $this->extractTitle($content);
            if ($newTitle !== 'Без названия') {
                $title = $newTitle;
            }
        }

        $paragraphs = preg_split("/\n\s*\n/", $content);
        $chunks = [];

        $maxChunkSize = config('ai.vector_store.max_chunk_size', 1000);
        $overlapSize = config('ai.vector_store.overlap_size', 200);

        foreach ($paragraphs as $paragraph) {
            $paragraph = trim($paragraph);
            if (empty($paragraph) || mb_strlen($paragraph) < 50) {
                continue;
            }

            if (mb_strlen($paragraph) <= $maxChunkSize) {
                $chunks[] = "Документ: {$title}\n\n" . $paragraph;
            } else {
                // Split large paragraph into smaller chunks with overlap
                $start = 0;
                while ($start < mb_strlen($paragraph)) {
                    $chunk = mb_substr($paragraph, $start, $maxChunkSize);
                    $chunks[] = "Документ: {$title}\n\n" . $chunk;
                    $start += ($maxChunkSize - $overlapSize);

                    // Avoid tiny last chunk
                    if (mb_strlen($paragraph) - $start < $overlapSize) {
                        break;
                    }
                }
            }
        }

        $finalChunks = [];
        $finalChunks[] = "Документ: {$title}";

        foreach ($chunks as $chunk) {
            $finalChunks[] = $chunk;
        }

        // Increase timeout for large batches to avoid Ollama cURL 28 timeouts
        $response = Ai::embeddings($finalChunks, null, null, 300);
        $embeddings = $response->embeddings; // array<int, array<float>>
        $storedDocuments = new Collection();

        foreach ($finalChunks as $index => $chunk) {
            $storedDocuments->add(Document::create([
                'content' => $chunk,
                'metadata' => array_merge($metadata, [
                    'document_id' => $documentId,
                    'chunk_index' => $index,
                ]),
                'embedding' => new Vector(
                    $this->normalize($embeddings[$index] ?? $response->first())
                ),
            ]));
        }

        return $storedDocuments;
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
        $results = $results->filter(fn ($r) => $r->distance < 0.35);

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
