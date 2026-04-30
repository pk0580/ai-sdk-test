<?php

declare(strict_types=1);

namespace App\Infrastructure\Ai\Memory;

use App\Domain\Ai\Knowledge\Embedding;
use App\Domain\Ai\Knowledge\EmbeddingProviderInterface;
use Laravel\Ai\Ai;

final class LaravelAiEmbeddingProvider implements EmbeddingProviderInterface
{
    public function embed(array $texts): array
    {
        $response = Ai::embeddings($texts, null, null, 300);
        $vectors = $response->embeddings;

        $result = [];
        foreach ($texts as $i => $_) {
            $values = $vectors[$i] ?? $response->first();
            $result[] = new Embedding($values);
        }

        return $result;
    }
}
