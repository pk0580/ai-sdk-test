<?php

namespace Tests\Unit;

use App\Ai\Core\DynamicPlanner;
use App\Ai\Core\State\AgentState;
use App\Ai\Agents\PlannerAgent;
use Laravel\Ai\Ai;
use Tests\TestCase;
use Mockery;

class DynamicPlannerTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_planner_stops_when_research_finished_is_present()
    {
        // Создаем состояние с меткой [RESEARCH_FINISHED] в истории
        $state = new AgentState(
            input: 'Поиск информации о HASHPASS',
            history: [
                [
                    'agent' => 'research',
                    'task' => 'Поиск информации о алгоритме формирования хеша пароля HASHPASS',
                    'result' => '[RESEARCH_FINISHED] Алгоритм формирования хеша пароля HASHPASS включает использование SALT для вычисления HMAC-SHA256.',
                    'success' => true
                ]
            ]
        );

        // Настройка DynamicPlanner через PlannerAgent fake
        Ai::fakeAgent(PlannerAgent::class, [
            [
                'result' => json_encode([
                    'next_step' => ['agent' => 'research', 'task' => 'еще раз поищи'],
                    'thought' => 'мне мало данных'
                ])
            ]
        ]);

        $planner = new DynamicPlanner();

        $nextStep = $planner->decideNextStep($state);

        // Теперь DynamicPlanner ДОЛЖЕН перехватить это и вернуть summary
        $this->assertNotNull($nextStep);
        $this->assertEquals('summary', $nextStep?->agent, "Planner should have changed research to summary because of [RESEARCH_FINISHED] tag");
        $this->assertStringContainsString('Подведи итог', $nextStep?->task);
    }

    public function test_planner_stops_after_summary()
    {
        // Создаем состояние, где summary уже есть в истории
        $state = new AgentState(
            input: 'Поиск информации о HASHPASS',
            history: [
                [
                    'agent' => 'research',
                    'task' => 'Поиск информации о алгоритме формирования хеша пароля HASHPASS',
                    'result' => 'Данные найдены.',
                    'success' => true
                ],
                [
                    'agent' => 'summary',
                    'task' => 'Подведи итог',
                    'result' => 'Вот твой отчет.',
                    'success' => true
                ]
            ]
        );

        $planner = new DynamicPlanner();
        $nextStep = $planner->decideNextStep($state);

        // Должен вернуть null, так как summary уже был и он был успешным (логика в AgentState::isFinished)
        // Хотя планировщик может еще не видеть что finished если мы просто смотрим историю в decideNextStep
        // Но в DynamicPlanner.php пока нет явной проверки summary в истории для возврата null
        // Там есть только: если данных достаточно (>=3) или есть метка [RESEARCH_FINISHED] -> summary
        // А для возврата null он спрашивает LLM

        $this->assertNull($nextStep, "Planner should return null after summary agent has finished");
    }

    public function test_planner_switches_to_summary_on_too_much_history()
    {
        // Создаем состояние с длинной историей
        $state = new AgentState(
            input: 'Поиск информации о HASHPASS',
            history: [
                ['agent' => 'research', 'task' => 'Поиск 1', 'result' => 'Данные 1', 'success' => true],
                ['agent' => 'research', 'task' => 'Поиск 2', 'result' => 'Данные 2', 'success' => true],
                ['agent' => 'research', 'task' => 'Поиск 3', 'result' => 'Данные 3', 'success' => true],
            ]
        );

        $planner = new DynamicPlanner();
        $nextStep = $planner->decideNextStep($state);

        // Должен переключиться на summary (count >= 3)
        $this->assertNotNull($nextStep);
        $this->assertEquals('summary', $nextStep?->agent);
    }
}
