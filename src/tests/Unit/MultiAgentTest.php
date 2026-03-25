<?php

namespace Tests\Unit;

use App\Ai\Agents\CheapAnonymousAgent;
use App\Ai\Agents\SmartAnonymousAgent;
use Tests\TestCase;
use App\Ai\Agents\ResearchAgent;
use App\Ai\Agents\SummaryAgent;
use App\Ai\Core\Supervisor;
use Laravel\Ai\AnonymousAgent;
use Mockery;

use Laravel\Ai\Ai;

class MultiAgentTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_supervisor_chooses_single_agent()
    {
        // 1. Supervisor decision (AnonymousAgent)
        AnonymousAgent::fake([
            ['type' => 'single', 'agent' => 'research']
        ]);

        // 2. Planner plan (CheapAnonymousAgent inside ResearchAgent's LoopController)
        CheapAnonymousAgent::fake([
            ['steps' => [
                ['tool' => 'calculator', 'parameters' => ['expression' => '2+2'], 'description' => 'Calculate']
            ]],
            ['decision' => 'finish', 'thought' => 'Done']
        ]);

        // 3. Responder Agent (SmartAnonymousAgent inside ResearchAgent's LoopController)
        SmartAnonymousAgent::fake([
            'Final single response'
        ]);

        $researchAgent = app(ResearchAgent::class);
        $summaryAgent = app(SummaryAgent::class);
        $supervisor = new Supervisor($researchAgent, $summaryAgent);

        $response = $supervisor->handle("Simple task");

        $this->assertEquals('Final single response', $response);
    }

    public function test_supervisor_runs_chain_of_agents()
    {
        // 1. Planner (CheapAnonymousAgent in ResearchAgent)
        CheapAnonymousAgent::fake([
            ['steps' => []]
        ]);

        // 2. Responder Agent (SmartAnonymousAgent in ResearchAgent)
        SmartAnonymousAgent::fake([
            'Detailed research data'
        ]);

        Ai::fakeAgent(SummaryAgent::class, [
            // SummaryAgent direct answer via ask() -> prompt()
            ['result' => 'Short summary']
        ]);

        $researchAgent = app(ResearchAgent::class);
        $summaryAgent = app(SummaryAgent::class);
        $supervisor = new Supervisor($researchAgent, $summaryAgent);

        $response = $supervisor->handle("Research and summarize Laravel");

        $this->assertStringContainsString('Short summary', $response);
    }
}
