<?php

namespace App\Ai\Tools;

use App\Ai\Core\QueryRewriter;
use App\Ai\Core\Reranker;
use App\Ai\Memory\VectorStore;
use App\Models\Document;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class VectorSearchTool implements Tool
{
    public function __construct(
        protected VectorStore $vectorStore,
        protected QueryRewriter $rewriter,
        protected Reranker $reranker
    ) {}

    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'Searches the knowledge base for relevant information about a specific query.';
    }

    public function handle(Request $request): Stringable|string
    {
        $query = $request->string('query');
        \Illuminate\Support\Facades\Log::info("Tool [VectorSearchTool]: Вызов с параметром query='{$query}'");
        $limit = $request->integer('limit', 5); // ТЗ предлагает 5 по умолчанию в примере

        if (Document::count() === 0) {
            return "Knowledge base is empty. No documents have been indexed yet.";
        }

        // ✅ Query Rewriting
        $rewrittenQuery = $this->rewriter->rewrite($query);

        $results = $this->vectorStore->search($rewrittenQuery, 10); // Берем чуть больше для реранкера

        // ✅ Reranking
        $results = $this->reranker->rerank($rewrittenQuery, $results);

        if ($results->isEmpty()) {
            return "No relevant information found for the query: '{$query}'.";
        }

        return $results->map(function ($doc) {
            return "--- DOCUMENT ---\n" . $doc->content;
        })->implode("\n\n");
    }

    /**
     * Get the tool's schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()
                ->description('The search query to find relevant information.')
                ->required(),
            'limit' => $schema->integer()
                ->description('Number of results to return (default: 3).'),
        ];
    }
}
