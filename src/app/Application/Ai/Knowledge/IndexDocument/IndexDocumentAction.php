<?php

declare(strict_types=1);

namespace App\Application\Ai\Knowledge\IndexDocument;

use App\Domain\Ai\Knowledge\DocumentRepositoryInterface;

final readonly class IndexDocumentAction
{
    public function __construct(private DocumentRepositoryInterface $documents) {}

    public function handle(IndexDocumentData $input): int
    {
        $chunks = $this->documents->add(
            content: $input->content,
            metadata: $input->metadata,
            documentId: $input->documentId,
        );

        return count($chunks);
    }
}
