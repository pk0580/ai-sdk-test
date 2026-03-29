<?php

namespace Tests\Unit;

use App\Ai\Agents\CheapAnonymousAgent;
use App\Ai\Agents\SmartAnonymousAgent;
use App\Ai\Core\Interfaces\DynamicPlannerInterface;
use App\Ai\Core\Plans\OrchestrationStep;
use Tests\TestCase;
use App\Ai\Agents\ResearchAgent;
use App\Ai\Agents\SummaryAgent;
use App\Ai\Core\Supervisor;
use Laravel\Ai\Ai;
use Mockery;

class MultiAgentTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_supervisor_runs_dynamic_plan()
    {
        // 1. Mock Dynamic Planner
        $planner = Mockery::mock(DynamicPlannerInterface::class);
        $planner->shouldReceive('initialStep')
            ->once()
            ->andReturn(new OrchestrationStep('research', 'Simple task'));

        $planner->shouldReceive('nextStep')
            ->once()
            ->andReturn(null); // Finish immediately after first step

        // 2. Mock Agents logic (Cheap/Smart agents inside ResearchAgent's LoopController)
        CheapAnonymousAgent::fake([
            ['steps' => []]
        ]);
        SmartAnonymousAgent::fake([
            'Final response'
        ]);

        $researchAgent = app(ResearchAgent::class);
        $summaryAgent = app(SummaryAgent::class);
        $supervisor = new Supervisor($researchAgent, $summaryAgent, $planner);

        $state = $supervisor->handle("Simple task");

        $this->assertEquals('Final response', $state->context);
        $this->assertCount(1, $state->history);
    }
}
