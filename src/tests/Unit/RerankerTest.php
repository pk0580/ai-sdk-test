<?php

namespace Tests\Unit;

use App\Ai\Core\Reranker;
use App\Models\Document;
use Illuminate\Support\Collection;
use Tests\TestCase;
use Mockery;
use App\Ai\Agents\SmartAnonymousAgent;

class RerankerTest extends TestCase
{
    public function test_rerank_parses_ids_correctly()
    {
        $chunks = collect([
            new Document(['content' => 'Content 1']),
            new Document(['content' => 'Content 2']),
            new Document(['content' => 'Content 3']),
        ]);
        // Manually set IDs because we don't want to hit DB
        $chunks[0]->id = 10;
        $chunks[1]->id = 20;
        $chunks[2]->id = 30;

        // Mock SmartAnonymousAgent
        $mockAgent = Mockery::mock('overload:' . SmartAnonymousAgent::class);
        $mockAgent->shouldReceive('prompt')
            ->once()
            ->andReturn('Selected IDs: 30, 10');

        $reranker = new Reranker();
        $result = $reranker->rerank('some query', $chunks);

        $this->assertCount(2, $result);
        $this->assertEquals(30, $result->first()->id);
        $this->assertEquals(10, $result->last()->id);
    }

    public function test_rerank_returns_top_3_on_parse_failure()
    {
        $chunks = collect([
            new Document(['content' => 'C1']),
            new Document(['content' => 'C2']),
            new Document(['content' => 'C3']),
            new Document(['content' => 'C4']),
        ]);
        foreach ($chunks as $i => $c) $c->id = $i + 1;

        $mockAgent = Mockery::mock('overload:' . SmartAnonymousAgent::class);
        $mockAgent->shouldReceive('prompt')
            ->once()
            ->andReturn('No IDs here');

        $reranker = new Reranker();
        $result = $reranker->rerank('query', $chunks);

        $this->assertCount(3, $result);
        $this->assertEquals(1, $result->first()->id);
    }
}
