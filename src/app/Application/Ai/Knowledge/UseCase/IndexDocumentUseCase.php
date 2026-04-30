<?php

declare(strict_types=1);

namespace App\Application\Ai\Knowledge\UseCase;

use App\Application\Ai\Knowledge\DTO\IndexDocumentInput;
use App\Domain\Ai\Knowledge\DocumentRepositoryInterface;

final class IndexDocumentUseCase
{
    public function __construct(private readonly DocumentRepositoryInterface $documents) {}

    public function execute(IndexDocumentInput $input): int
    {
        $chunks = $this->documents->add(
            content: $input->content,
            metadata: $input->metadata,
            documentId: $input->documentId,
        );

        return count($chunks);
    }
}
