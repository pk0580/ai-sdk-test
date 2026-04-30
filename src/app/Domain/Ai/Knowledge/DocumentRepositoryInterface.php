<?php

declare(strict_types=1);

namespace App\Domain\Ai\Knowledge;

interface DocumentRepositoryInterface
{
    /** @return DocumentChunk[] persisted chunks of the indexed content */
    public function add(string $content, array $metadata = [], ?string $documentId = null): array;

    /** @return DocumentChunk[] */
    public function search(SearchQuery $query): array;

    public function count(): int;
}
