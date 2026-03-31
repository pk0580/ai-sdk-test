<?php

namespace Tests\Unit;

use App\Ai\Core\QueryRewriter;
use App\Ai\Core\Reranker;
use Tests\TestCase;
use App\Models\Document;
use App\Ai\Memory\VectorStore;
use App\Ai\Tools\VectorSearchTool;
use Laravel\Ai\Ai;
use Laravel\Ai\Tools\Request;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;

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
        // Используем текст длиннее 50 символов
        $content = "This is a long enough paragraph to pass the length filter of the VectorStore. It should be at least fifty characters long.\n\n" .
                   "This is another long paragraph to ensure we have multiple chunks and it also passes the filter.";
        $docs = $store->add($content, ['source' => 'manual'], 'doc1');

        $this->assertInstanceOf(Collection::class, $docs);
        // add() добавляет Title чанк + отфильтрованные чанки
        $this->assertGreaterThanOrEqual(2, $docs->count());

        // Поиск должен найти что-то, так как мы используем подстроку из контента
        // В VectorStore search расстояние должно быть < 0.35
        // При фейковых эмбеддингах 0.1, расстояние 0
        $results = $store->search('paragraph', 1);
        $this->assertCount(1, $results);
    }

    public function test_vector_store_deduplication()
    {
        Ai::fakeEmbeddings(function ($inputs) {
            return array_map(fn() => array_fill(0, 768, 0.1), $inputs);
        });

        $store = new VectorStore();
        // Добавляем длинные параграфы для doc1
        $longP = str_repeat("Long paragraph content ", 5);
        $store->add("{$longP} 1\n\n{$longP} 2\n\n{$longP} 3", ['title' => 'Title One'], 'doc1');
        // Добавляем длинный параграф для doc2 с ТЕМ ЖЕ контентом для проверки дедупликации
        $store->add("{$longP} 1", ['title' => 'Title Two'], 'doc2');

        // Поиск по ключевому слову 'paragraph'
        $results = $store->search('paragraph', 10);

        // В VectorStore дедупликация по 'content'
        // У нас 3 уникальных контента: "Title One", "Long... 1", "Long... 2", "Long... 3", "Title Two"
        // "Long... 1" повторяется в doc1 и doc2
        $uniqueContents = $results->pluck('content')->unique();
        $this->assertEquals($results->count(), $uniqueContents->count());
    }

    public function test_vector_search_tool_returns_formatted_results()
    {
        Ai::fakeEmbeddings(function ($inputs) {
            return array_map(fn() => array_fill(0, 768, 0.1), $inputs);
        });

        $store = new VectorStore();
        $longText = "This is a very long document content to pass the length filter. " . str_repeat("More content to ensure it's long enough. ", 3);
        $store->add($longText . ' 1', ['title' => 'Doc1'], 'd1');
        $store->add($longText . ' 2', ['title' => 'Doc2'], 'd2');

        $rewriter = \Mockery::mock(QueryRewriter::class);
        $rewriter->shouldReceive('rewrite')->andReturn('document');

        $reranker = \Mockery::mock(Reranker::class);
        $reranker->shouldReceive('rerank')->andReturnUsing(fn($q, $c) => $c->take(2));

        $tool = new VectorSearchTool($store, $rewriter, $reranker);

        $request = new Request(['query' => 'document', 'limit' => 2]);
        $result = $tool->handle($request);

        $this->assertStringContainsString('--- DOCUMENT ---', $result);
        $this->assertStringContainsString('Doc1', $result);
        $this->assertStringContainsString('Doc2', $result);
    }

    public function test_vector_search_tool_returns_not_found_message()
    {
        // Mock the embeddings creation
        Ai::fakeEmbeddings(function ($inputs) {
            return array_map(fn() => array_fill(0, 768, 0.1), $inputs);
        });

        // Создаем мок для VectorStore, чтобы он возвращал пустую коллекцию
        $mockStore = \Mockery::mock(VectorStore::class);
        $mockStore->shouldReceive('search')->andReturn(collect([]));

        $rewriter = \Mockery::mock(QueryRewriter::class);
        $rewriter->shouldReceive('rewrite')->andReturn('nonexistent');

        $reranker = \Mockery::mock(Reranker::class);
        $reranker->shouldReceive('rerank')->andReturn(collect([]));

        // Нам нужно, чтобы Document::count() > 0
        Document::create([
            'content' => 'dummy',
            'embedding' => new \Pgvector\Laravel\Vector(array_fill(0, 768, 0.1))
        ]);

        $tool = new VectorSearchTool($mockStore, $rewriter, $reranker);

        $request = new Request(['query' => 'nonexistent', 'limit' => 1]);
        $result = $tool->handle($request);

        $this->assertEquals("No relevant information found for the query: 'nonexistent'.", $result);
    }
}
