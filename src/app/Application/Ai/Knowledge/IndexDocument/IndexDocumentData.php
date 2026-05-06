<?php

declare(strict_types=1);

namespace App\Application\Ai\Knowledge\IndexDocument;

final readonly class IndexDocumentData
{
    public function __construct(
        public string  $content,
        public array   $metadata = [],
        public ?string $documentId = null,
    ) {}
}
