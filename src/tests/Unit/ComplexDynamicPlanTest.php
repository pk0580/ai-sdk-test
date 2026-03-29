<?php

namespace Tests\Unit;

use App\Ai\Agents\CheapAnonymousAgent;
use App\Ai\Agents\SmartAnonymousAgent;
use App\Ai\Core\Supervisor;
use Tests\TestCase;
use App\Ai\Agents\ResearchAgent;
use App\Ai\Agents\SummaryAgent;
use App\Ai\Agents\PlannerAgent;
use App\Ai\Core\DynamicPlanner;
use Laravel\Ai\Ai;
use Mockery;

class ComplexDynamicPlanTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_complex_dynamic_plan_with_refinement()
    {
        // 1. Мокаем работу ResearchAgent (LoopController)
        // Для первого шага (поиск прибыли)
        CheapAnonymousAgent::fake([
            ['steps' => []], // Нет инструментов
        ]);
        SmartAnonymousAgent::fake([
            'Profit in 2023 was $1M.', // Результат 1 шага
            'Revenue in 2023 was $5M. Net profit margin 20%.', // Результат 2 шага (уточнение)
        ]);

        // 2. Мокаем DynamicPlanner (через PlannerAgent внутри него)
        Ai::fakeAgent(PlannerAgent::class, [
            // После 1-го шага (research прибыли) -> Планировщик хочет уточнить выручку
            ['result' => json_encode([
                'next_step' => ['agent' => 'research', 'task' => 'Теперь найди выручку за тот же период'],
                'thought' => 'Нужна выручка для полноты картины.'
            ])],
            // После 2-го шага (research выручки) -> Планировщик хочет саммари
            ['result' => json_encode([
                'next_step' => ['agent' => 'summary', 'task' => 'Сделай итоговый финансовый отчет'],
                'thought' => 'Данных достаточно для отчета.'
            ])],
            // После 3-го шага (summary) -> Финиш
            ['result' => json_encode(['finish' => true, 'thought' => 'Отчет готов.'])]
        ]);

        // Mock SummaryAgent
        Ai::fakeAgent(SummaryAgent::class, [
            ['result' => 'FINANCIAL REPORT: Revenue $5M, Profit $1M.']
        ]);

        $researchAgent = app(ResearchAgent::class);
        $summaryAgent = app(SummaryAgent::class);
        $planner = new DynamicPlanner();
        $supervisor = new Supervisor($researchAgent, $summaryAgent, $planner);

        $state = $supervisor->handle("Проанализируй финансы за 2023 год");

        // Проверяем количество шагов: research -> research -> summary
        $this->assertCount(3, $state->history);
        $this->assertEquals('research', $state->history[0]['agent']);
        $this->assertEquals('research', $state->history[1]['agent']);
        $this->assertEquals('summary', $state->history[2]['agent']);

        $this->assertStringContainsString('FINANCIAL REPORT', $state->context);
        $this->assertStringContainsString('Revenue $5M', $state->context);
    }
}
