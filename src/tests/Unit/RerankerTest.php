<?php

namespace Tests\Unit;

use App\Domain\Ai\Knowledge\DocumentChunk;
use App\Infrastructure\Ai\Agent\SmartAnonymousAgent;
use App\Infrastructure\Ai\Reranker\LlmReranker;
use Tests\TestCase;
use Mockery;

class RerankerTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_rerank_parses_ids_correctly()
    {
        $chunks = [
            new DocumentChunk(10, 'Content 1'),
            new DocumentChunk(20, 'Content 2'),
            new DocumentChunk(30, 'Content 3'),
        ];

        // Mock SmartAnonymousAgent
        $mockAgent = Mockery::mock('overload:' . SmartAnonymousAgent::class);
        $mockAgent->shouldReceive('prompt')
            ->once()
            ->andReturn('Selected IDs: 30, 10');

        $reranker = new LlmReranker();
        $result = $reranker->rerank('some query', $chunks);

        $this->assertCount(2, $result);
        $this->assertEquals(30, $result[0]->id);
        $this->assertEquals(10, $result[1]->id);
    }

    public function test_rerank_returns_top_3_on_parse_failure()
    {
        $chunks = [
            new DocumentChunk(1, 'C1'),
            new DocumentChunk(2, 'C2'),
            new DocumentChunk(3, 'C3'),
            new DocumentChunk(4, 'C4'),
            new DocumentChunk(5, 'C5'),
            new DocumentChunk(6, 'C6'),
        ];

        $mockAgent = Mockery::mock('overload:' . SmartAnonymousAgent::class);
        $mockAgent->shouldReceive('prompt')
            ->once()
            ->andReturn('No IDs here');

        $reranker = new LlmReranker();
        $result = $reranker->rerank('query', $chunks);

        // LlmReranker::MAX_RESULTS is 5
        $this->assertCount(5, $result);
        $this->assertEquals(1, $result[0]->id);
    }
}
