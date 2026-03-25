<?php

namespace Tests\Unit;

use Laravel\Ai\AnonymousAgent;
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

        AnonymousAgent::fake([
            $jsonResponse
        ]);

        $planner = $this->getPlanner();
        $plan = $planner->generate('How much is 25 * 17?');

        $this->assertInstanceOf(Plan::class, $plan);
        $this->assertCount(1, $plan->steps);
        $this->assertEquals('calculator', $plan->steps->first()->tool);
        $this->assertEquals(['expression' => '25 * 17'], $plan->steps->first()->parameters);
    }

    public function test_planner_returns_fallback_on_invalid_json()
    {
        AnonymousAgent::fake([
            'Invalid JSON here'
        ]);

        $planner = $this->getPlanner();
        $plan = $planner->generate('test');

        $this->assertInstanceOf(Plan::class, $plan);
        $this->assertEquals('vector_search', $plan->steps->first()->tool);
    }

    public function test_planner_returns_fallback_on_exception()
    {
        // Для симуляции ошибки (Exception) в AnonymousAgent::fake() можно передать Closure,
        // который выбрасывает исключение, но SDK обычно перехватывает его внутри prompt().
        // Однако в нашем случае Planner перехватывает любое исключение.

        AnonymousAgent::fake(function() {
            throw new \Exception('AI Error');
        });

        $planner = $this->getPlanner();
        $plan = $planner->generate('test');

        $this->assertInstanceOf(Plan::class, $plan);
        $this->assertEquals('vector_search', $plan->steps->first()->tool);
    }
}
