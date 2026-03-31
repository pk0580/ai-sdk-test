<?php

namespace Tests\Unit;

use App\Ai\Agents\CheapAnonymousAgent;
use Tests\TestCase;
use App\Ai\Core\ToolsPlanner;
use App\Ai\DTO\Plan;
use Mockery;
use App\Ai\Tools\ToolRegistry;

class ToolsPlannerTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    protected function getPlanner(): ToolsPlanner
    {
        $registry = new ToolRegistry();
        return new ToolsPlanner($registry);
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

        CheapAnonymousAgent::fake([
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
        CheapAnonymousAgent::fake([
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
        // Однако в нашем случае ToolsPlanner перехватывает любое исключение.

        CheapAnonymousAgent::fake(function() {
            throw new \Exception('AI Error');
        });

        $planner = $this->getPlanner();
        $plan = $planner->generate('test');

        $this->assertInstanceOf(Plan::class, $plan);
        $this->assertEquals('vector_search', $plan->steps->first()->tool);
    }
}
