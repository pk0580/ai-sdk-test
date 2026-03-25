<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Document;
use App\Ai\Memory\VectorStore;
use App\Ai\Tools\VectorSearchTool;
use Laravel\Ai\Ai;
use Laravel\Ai\Tools\Request;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Pgvector\Laravel\Vector;

class RagTest extends TestCase
{
    use RefreshDatabase;

    public function test_vector_store_can_add_and_search_documents()
    {
        // Mock the embeddings creation
        Ai::fakeEmbeddings(function ($inputs) {
            return array_map(fn() => array_fill(0, 768, 0.1), $inputs);
        });

        $store = new VectorStore();
        $docs = $store->add("Paragraph 1\n\nParagraph 2", ['source' => 'manual'], 'doc1');

        $this->assertInstanceOf(Collection::class, $docs);
        $this->assertCount(2, $docs);
        $this->assertEquals('Paragraph 1', $docs[0]->content);
        $this->assertStringContainsString('Paragraph 2', $docs[1]->content);
        $this->assertEquals('doc1', $docs[0]->metadata['document_id']);
        $this->assertEquals(0, $docs[0]->metadata['chunk_index']);
        $this->assertEquals(1, $docs[1]->metadata['chunk_index']);

        $results = $store->search('test query', 1);
        $this->assertCount(1, $results);
    }

    public function test_vector_store_deduplication()
    {
        Ai::fakeEmbeddings(function ($inputs) {
            return array_map(fn() => array_fill(0, 768, 0.1), $inputs);
        });

        $store = new VectorStore();
        // Add 3 chunks for doc1
        $store->add("P1\n\nP2\n\nP3", [], 'doc1');
        // Add 1 chunk for doc2
        $store->add("P4", [], 'doc2');

        // Search with limit 5, but perDocumentLimit 2
        // Should return 2 chunks from doc1 and 1 chunk from doc2
        $results = $store->search('test query', 5, 2);

        $this->assertCount(3, $results);
        $doc1Chunks = $results->filter(fn($doc) => $doc->metadata['document_id'] === 'doc1');
        $doc2Chunks = $results->filter(fn($doc) => $doc->metadata['document_id'] === 'doc2');

        $this->assertCount(2, $doc1Chunks);
        $this->assertCount(1, $doc2Chunks);
    }

    public function test_vector_search_tool_returns_formatted_results()
    {
        Ai::fakeEmbeddings(function ($inputs) {
            return array_map(fn() => array_fill(0, 768, 0.1), $inputs);
        });

        $store = new VectorStore();
        $store->add('Document 1');
        $store->add('Document 2');

        $tool = new VectorSearchTool($store);

        $request = new Request(['query' => 'test query', 'limit' => 2]);
        $result = $tool->handle($request);

        $this->assertStringContainsString('--- DOCUMENT ---', $result);
        $this->assertStringContainsString('Document 1', $result);
        $this->assertStringContainsString('Document 2', $result);
    }

    public function test_vector_search_tool_returns_not_found_message()
    {
        Ai::fakeEmbeddings(function ($inputs) {
            return array_map(fn() => array_fill(0, 768, 0.1), $inputs);
        });

        $store = new VectorStore();
        $tool = new VectorSearchTool($store);

        $request = new Request(['query' => 'empty', 'limit' => 1]);
        $result = $tool->handle($request);

        $this->assertEquals('No relevant information found.', $result);
    }
}
