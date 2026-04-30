<?php

declare(strict_types=1);

namespace App\Domain\Ai\Knowledge;

interface EmbeddingProviderInterface
{
    /**
     * @param string[] $texts
     * @return Embedding[] same order as $texts
     */
    public function embed(array $texts): array;
}
