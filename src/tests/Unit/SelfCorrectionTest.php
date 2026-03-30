<?php

namespace Tests\Unit;

use App\Ai\Agents\ResearchAgent;
use App\Ai\Agents\SummaryAgent;
use App\Ai\Core\DynamicPlanner;
use App\Ai\Core\OrchestrationExecutor;
use App\Ai\Core\State\AgentState;
use App\Ai\Core\Supervisor;
use App\Ai\Core\Plans\OrchestrationStep;
use App\Ai\Agents\PlannerAgent;
use Mockery;
use Tests\TestCase;
use Exception;

class SelfCorrectionTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_supervisor_handles_agent_error_and_continues()
    {
        $researchAgent = Mockery::mock(ResearchAgent::class);
        $summaryAgent = Mockery::mock(SummaryAgent::class);
        $planner = Mockery::mock(DynamicPlanner::class);

        // Первый шаг - research
        $initialStep = new OrchestrationStep('research', 'Initial Task');
        $planner->shouldReceive('initialStep')->once()->andReturn($initialStep);

        // ResearchAgent выбрасывает ошибку
        $researchAgent->shouldReceive('execute')->once()->andThrow(new Exception("API Limit Reached"));

        // После ошибки Supervisor должен вызвать nextStep с информацией об ошибке в стейте
        $planner->shouldReceive('nextStep')
            ->once()
            ->with(Mockery::on(function (AgentState $state) {
                return isset($state->history[0]['error']) && $state->history[0]['error'] === 'API Limit Reached';
            }))
            ->andReturn(new OrchestrationStep('summary', 'Final Summary despite error'));

        // Затем выполняется SummaryAgent
        $summaryAgent->shouldReceive('execute')->once()->andReturn("Final result after correction");

        // И наконец планировщик завершает работу
        $planner->shouldReceive('nextStep')->once()->andReturn(null);

        $supervisor = new Supervisor($researchAgent, $summaryAgent, $planner);

        // В Supervisor конструктор создает OrchestrationExecutor, нам нужно убедиться, что он использует наши моки
        // Но Supervisor инжектит агентов в массив.

        $state = $supervisor->handle("Test message");

        $this->assertEquals("Final result after correction", $state->context);
        $this->assertCount(2, $state->history);
        $this->assertEquals('failed', $state->history[0]['status']);
    }
}
