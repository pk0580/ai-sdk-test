<?php

declare(strict_types=1);

namespace App\Domain\Ai\Knowledge;

final readonly class Document
{
    public function __construct(
        public int|string|null $id,
        public string          $content,
        public array           $metadata,
        public ?Embedding      $embedding = null,
    ) {}
}
