<?php

namespace Tests\Unit;

use App\Ai\Agents\CheapAnonymousAgent;
use App\Ai\Agents\SmartAnonymousAgent;
use App\Ai\Core\Supervisor;
use App\Ai\Core\State\AgentState;
use Tests\TestCase;
use App\Ai\Agents\ResearchAgent;
use App\Ai\Agents\SummaryAgent;
use App\Ai\Agents\PlannerAgent;
use App\Ai\Core\DynamicPlanner;
use Laravel\Ai\Ai;
use Mockery;

class DynamicPlanTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_dynamic_plan_execution()
    {
        // 1. Подготовка агентов
        // ResearchAgent's LoopController uses CheapAnonymousAgent for planning
        CheapAnonymousAgent::fake([
            ['steps' => []], // Research step 1: no tools
        ]);

        // ResearchAgent's LoopController uses SmartAnonymousAgent for final response
        SmartAnonymousAgent::fake([
            'Research results: Profit is 100$', // Step 1 result
            'Summary: The company is doing well with 100$ profit.' // Step 2 result (if summary used)
        ]);

        // 2. Настройка DynamicPlanner через PlannerAgent fake
        // Первый вызов nextStep будет после research
        // Второй вызов nextStep будет после summary
        Ai::fakeAgent(PlannerAgent::class, [
            // Ответ для первого вызова nextStep (после research)
            ['result' => json_encode(['next_step' => ['agent' => 'summary', 'task' => 'Сделай вывод']])],
            // Ответ для второго вызова nextStep (после summary)
            ['result' => json_encode(['finish' => true])]
        ]);

        // Mock SummaryAgent
        Ai::fakeAgent(SummaryAgent::class, [
            ['result' => 'Summary: The company is doing well with 100$ profit.']
        ]);

        $researchAgent = app(ResearchAgent::class);
        $summaryAgent = app(SummaryAgent::class);
        $planner = new DynamicPlanner();
        $supervisor = new Supervisor($researchAgent, $summaryAgent, $planner);

        $state = $supervisor->handle("Найди прибыль и сделай вывод");

        // Проверяем, что было выполнено 2 шага
        $this->assertCount(2, $state->history);
        $this->assertEquals('research', $state->history[0]['agent']);
        $this->assertEquals('summary', $state->history[1]['agent']);
        $this->assertStringContainsString('Summary', $state->context);
    }
}
