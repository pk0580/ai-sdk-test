<?php

namespace App\Ai\Memory;

use App\Models\Document;
use Laravel\Ai\Ai;
use Pgvector\Laravel\Vector;
use Illuminate\Support\Collection;

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
        $paragraphs = preg_split("/\n\s*\n/", $content);
        $chunks = [];

        $maxChunkSize = 1000; // Character count (approximate tokens)
        $overlapSize = 200;

        foreach ($paragraphs as $paragraph) {
            $paragraph = trim($paragraph);
            if (empty($paragraph)) {
                continue;
            }

            if (mb_strlen($paragraph) <= $maxChunkSize) {
                $chunks[] = $paragraph;
            } else {
                // Split large paragraph into smaller chunks with overlap
                $start = 0;
                while ($start < mb_strlen($paragraph)) {
                    $chunk = mb_substr($paragraph, $start, $maxChunkSize);
                    $chunks[] = $chunk;
                    $start += ($maxChunkSize - $overlapSize);

                    // Avoid tiny last chunk
                    if (mb_strlen($paragraph) - $start < $overlapSize) {
                        break;
                    }
                }
            }
        }

        // Add overlap between paragraphs (simplified: the loop above already handles internal paragraph overlap)
        // To maintain previous behavior of overlapping between DIFFERENT paragraphs:
        $finalChunks = [];
        for ($i = 0; $i < count($chunks); $i++) {
            $currentChunk = $chunks[$i];
            if ($i > 0) {
                $prevChunk = $chunks[$i - 1];
                $overlap = mb_substr($prevChunk, -min(mb_strlen($prevChunk), $overlapSize));
                $currentChunk = $overlap . "\n" . $currentChunk;
            }
            $finalChunks[] = $currentChunk;
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
                'embedding' => new Vector($embeddings[$index] ?? $response->first()),
            ]));
        }

        return $storedDocuments;
    }

    /**
     * Search for similar documents with deduplication (max N chunks per document).
     */
    public function search(string $query, int $limit = 5, int $perDocumentLimit = 2): Collection
    {
        // Increase timeout for single embedding generation as well
        $embedding = Ai::embeddings([$query], null, null, 300)->first();
        $vector = new Vector($embedding);

        $results = Document::query()
            ->nearestNeighbors('embedding', $vector, Distance::L2)
            ->limit($limit * 2) // Fetch more to allow for deduplication
            ->get();

        return $results->groupBy(fn ($doc) => $doc->metadata['document_id'] ?? uniqid())
            ->flatMap(fn ($docs) => $docs->take($perDocumentLimit))
            ->take($limit);
    }
}
