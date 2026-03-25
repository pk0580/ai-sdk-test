<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Ai\Agents\ResearchAgent;
use App\Ai\Agents\SummaryAgent;
use App\Ai\Core\LoopController;
use App\Ai\Core\Supervisor;
use Laravel\Ai\AnonymousAgent;
use Mockery;

use App\Ai\Tools\ToolRegistry;

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
        // 2. Planner plan (AnonymousAgent inside ResearchAgent's LoopController)
        // 3. Reflector (AnonymousAgent inside ResearchAgent's LoopController)
        // 4. Responder Agent (AnonymousAgent inside ResearchAgent's LoopController)
        AnonymousAgent::fake([
            ['type' => 'single', 'agent' => 'research'],
            ['steps' => [
                ['tool' => 'calculator', 'parameters' => ['expression' => '2+2'], 'description' => 'Calculate']
            ]],
            ['decision' => 'finish', 'thought' => 'Done'],
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
        // 1. Planner (for ResearchAgent)
        // 2. Responder Agent (for ResearchAgent)
        AnonymousAgent::fake([
            ['steps' => []],
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
