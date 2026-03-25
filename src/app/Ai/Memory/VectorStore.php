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

        for ($i = 0; $i < count($paragraphs); $i++) {
            $chunk = $paragraphs[$i];

            // Add overlap from previous paragraph if it exists
            if ($i > 0) {
                $prevParagraph = $paragraphs[$i - 1];
                $overlap = mb_substr($prevParagraph, -mb_strlen($prevParagraph) / 4); // 25% overlap
                $chunk = $overlap . "\n" . $chunk;
            }

            $chunks[] = $chunk;
        }

        $response = Ai::embeddings($chunks);
        $embeddings = $response->embeddings; // array<int, array<float>>
        $storedDocuments = new Collection();

        foreach ($chunks as $index => $chunk) {
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
        $embedding = Ai::embeddings([$query])->first();
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
