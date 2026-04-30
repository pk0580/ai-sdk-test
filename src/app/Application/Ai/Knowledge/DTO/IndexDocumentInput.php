<?php

declare(strict_types=1);

namespace App\Application\Ai\Knowledge\DTO;

final readonly class IndexDocumentInput
{
    public function __construct(
        public string  $content,
        public array   $metadata = [],
        public ?string $documentId = null,
    ) {}
}
