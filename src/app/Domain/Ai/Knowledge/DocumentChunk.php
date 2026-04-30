<?php

declare(strict_types=1);

namespace App\Domain\Ai\Knowledge;

/**
 * Read model used to pass retrieval results across the application
 * boundary without exposing the persistence layer.
 */
final readonly class DocumentChunk
{
    public function __construct(
        public int|string|null $id,
        public string          $content,
        public array           $metadata = [],
        public ?float          $distance = null,
    ) {}
}
