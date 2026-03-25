<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Ai\Core\Planner;
use App\Ai\DTO\Plan;
use App\Ai\DTO\Step;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Responses\AgentResponse;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\Data\Usage;
use Mockery;
use App\Ai\Tools\ToolRegistry;

class PlannerTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    protected function getPlanner(): Planner
    {
        $registry = new ToolRegistry();
        return new Planner($registry);
    }

    public function test_planner_generates_correct_plan_on_success()
    {
        $jsonResponse = json_encode([
            'steps' => [
                [
                    'tool' => 'calculator',
                    'parameters' => ['expression' => '25 * 17'],
                    'description' => 'Calculate the result'
                ]
            ]
        ]);

        $mockResponse = new AgentResponse(
            'inv_123',
            $jsonResponse,
            new Usage(),
            new Meta()
        );

        // Так как agent() создает new AnonymousAgent, мы всё равно используем overload.
        $mockAgent = Mockery::mock('overload:Laravel\Ai\AnonymousAgent');
        $mockAgent->shouldReceive('prompt')
            ->once()
            ->andReturn($mockResponse);

        $planner = $this->getPlanner();
        $plan = $planner->generate('How much is 25 * 17?');

        $this->assertInstanceOf(Plan::class, $plan);
        $this->assertCount(1, $plan->steps);
        $this->assertEquals('calculator', $plan->steps->first()->tool);
        $this->assertEquals(['expression' => '25 * 17'], $plan->steps->first()->parameters);
    }

    public function test_planner_returns_fallback_on_invalid_json()
    {
        $mockResponse = new AgentResponse(
            'inv_123',
            'Invalid JSON here',
            new Usage(),
            new Meta()
        );

        $mockAgent = Mockery::mock('overload:' . AnonymousAgent::class);
        $mockAgent->shouldReceive('prompt')
            ->once()
            ->andReturn($mockResponse);

        $planner = $this->getPlanner();
        $plan = $planner->generate('test');

        $this->assertInstanceOf(Plan::class, $plan);
        $this->assertEquals('vector_search', $plan->steps->first()->tool);
    }

    public function test_planner_returns_fallback_on_exception()
    {
        $mockAgent = Mockery::mock('overload:' . AnonymousAgent::class);
        $mockAgent->shouldReceive('prompt')
            ->once()
            ->andThrow(new \Exception('AI Error'));

        $planner = $this->getPlanner();
        $plan = $planner->generate('test');

        $this->assertInstanceOf(Plan::class, $plan);
        $this->assertEquals('vector_search', $plan->steps->first()->tool);
    }
}
