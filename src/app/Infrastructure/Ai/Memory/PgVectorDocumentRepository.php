<?php

declare(strict_types=1);

namespace App\Infrastructure\Ai\Memory;

use App\Domain\Ai\Knowledge\DocumentChunk;
use App\Domain\Ai\Knowledge\DocumentRepositoryInterface;
use App\Domain\Ai\Knowledge\EmbeddingProviderInterface;
use App\Domain\Ai\Knowledge\SearchQuery;
use App\Infrastructure\Persistence\Eloquent\Models\DocumentModel;
use Illuminate\Support\Str;
use Pgvector\Laravel\Vector;

final readonly class PgVectorDocumentRepository implements DocumentRepositoryInterface
{
    public function __construct(private EmbeddingProviderInterface $embeddings) {}

    public function add(string $content, array $metadata = [], ?string $documentId = null): array
    {
        $documentId = $documentId ?? (string) Str::uuid();
        $title = $this->resolveTitle($content, $metadata);

        $chunks = $this->prepareChunks($content, $title);

        $embeddings = $this->embeddings->embed($chunks);

        $persisted = [];

        foreach ($chunks as $index => $chunk) {
            $embedding = ($embeddings[$index] ?? $embeddings[0])->normalized();

            $model = DocumentModel::query()->create([
                'content' => $chunk,
                'metadata' => array_merge($metadata, [
                    'document_id' => $documentId,
                    'chunk_index' => $index,
                ]),
                'embedding' => new Vector($embedding->values),
            ]);

            $persisted[] = new DocumentChunk(
                id: $model->getKey(),
                content: $model->content,
                metadata: (array) $model->metadata,
            );
        }

        return $persisted;
    }

    public function search(SearchQuery $query): array
    {
        $vectors = $this->embeddings->embed([$query->value]);
        $vector = new Vector($vectors[0]->normalized()->values);

        $rows = DocumentModel::query()
            ->select('*')
            ->selectRaw('embedding <=> ? as distance', [$vector])
            ->orderByRaw('embedding <=> ?', [$vector])
            ->limit(50)
            ->get();

        $filtered = $rows
            ->filter(fn ($r) => $r->distance < 0.65)
            ->unique('content')
            ->sortBy('distance')
            ->take($query->limit);

        return $filtered
            ->map(fn ($r) => new DocumentChunk(
                id: $r->getKey(),
                content: $r->content,
                metadata: (array) $r->metadata,
                distance: (float) $r->distance,
            ))
            ->values()
            ->all();
    }

    public function count(): int
    {
        return DocumentModel::query()->count();
    }

    private function resolveTitle(string $content, array $metadata): string
    {
        $title = $metadata['title'] ?? '';

        if ($title === '' || in_array($title, ['Документ', 'Без названия'], true)) {
            $extracted = $this->extractTitle($content);
            if ($extracted !== 'Без названия') {
                return $extracted;
            }
        }

        return $title !== '' ? $title : 'Без названия';
    }

    /** @return string[] */
    private function prepareChunks(string $content, string $title): array
    {
        $maxChunkSize = (int) config('ai.vector_store.max_chunk_size', 1000);
        $overlapSize = (int) config('ai.vector_store.overlap_size', 200);

        $paragraphs = preg_split("/\n\s*\n/", $content) ?: [];
        $chunks = ["Документ: {$title}"];

        foreach ($paragraphs as $paragraph) {
            $paragraph = trim($paragraph);
            if ($paragraph === '' || mb_strlen($paragraph) < 50) {
                continue;
            }

            if (mb_strlen($paragraph) <= $maxChunkSize) {
                $chunks[] = "Документ: {$title}\n\n" . $paragraph;
                continue;
            }

            foreach ($this->splitLongParagraph($paragraph, $title, $maxChunkSize, $overlapSize) as $chunk) {
                $chunks[] = $chunk;
            }
        }

        return $chunks;
    }

    /** @return string[] */
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

    private function extractTitle(string $content): string
    {
        $lines = preg_split('/\n+/', $content) ?: [];

        foreach ($lines as $line) {
            $line = trim($line);

            if (
                mb_strlen($line) > 10
                && mb_strlen($line) < 120
                && !str_ends_with($line, '.')
            ) {
                return $line;
            }
        }

        return 'Без названия';
    }
}
