<?php

declare(strict_types=1);

namespace App\Infrastructure\Ai\Tool;

use App\Domain\Ai\Knowledge\DocumentRepositoryInterface;
use App\Domain\Ai\Knowledge\QueryRewriterInterface;
use App\Domain\Ai\Knowledge\RerankerInterface;
use App\Domain\Ai\Knowledge\SearchQuery;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

final readonly class VectorSearchTool implements Tool
{
    public function __construct(
        private DocumentRepositoryInterface $documents,
        private QueryRewriterInterface      $rewriter,
        private RerankerInterface           $reranker,
    ) {}

    public function description(): Stringable|string
    {
        return 'Searches the knowledge base for relevant information about a specific query.';
    }

    public function handle(Request $request): Stringable|string
    {
        $query = (string) $request->string('query');
        Log::info("Tool [VectorSearchTool]: called with query='{$query}'");

        if ($this->documents->count() === 0) {
            return 'Knowledge base is empty. No documents have been indexed yet.';
        }

        $rewritten = $this->rewriter->rewrite($query);

        $chunks = $this->documents->search(new SearchQuery($rewritten, 10));
        $chunks = $this->reranker->rerank($rewritten, $chunks);

        if ($chunks === []) {
            return "No relevant information found for the query: '{$query}'.";
        }

        $rendered = array_map(
            static fn ($chunk) => "--- DOCUMENT ---\n" . $chunk->content,
            $chunks,
        );

        return implode("\n\n", $rendered);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()
                ->description('The search query to find relevant information.')
                ->required(),
            'limit' => $schema->integer()
                ->description('Number of results to return (default: 5).'),
        ];
    }
}
