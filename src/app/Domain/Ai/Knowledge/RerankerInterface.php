<?php

declare(strict_types=1);

namespace App\Domain\Ai\Knowledge;

interface RerankerInterface
{
    /**
     * @param DocumentChunk[] $chunks
     * @return DocumentChunk[]
     */
    public function rerank(string $query, array $chunks): array;
}
