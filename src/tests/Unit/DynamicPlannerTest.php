<?php

namespace Tests\Unit;

use App\Ai\Core\DynamicPlanner;
use App\Ai\Core\Plans\OrchestrationStep;
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
            step: new OrchestrationStep('research', 'Поиск информации о алгоритме формирования хеша пароля HASHPASS'),
            context: '[RESEARCH_FINISHED] Алгоритм формирования хеша пароля HASHPASS включает использование SALT для вычисления HMAC-SHA256.',
            history: [
                [
                    'agent' => 'research',
                    'task' => 'Поиск информации о алгоритме формирования хеша пароля HASHPASS',
                    'result' => '[RESEARCH_FINISHED] Алгоритм формирования хеша пароля HASHPASS включает использование SALT для вычисления HMAC-SHA256.'
                ]
            ]
        );

        // Настройка DynamicPlanner через PlannerAgent fake
        // Ситуация: Агент "ошибается" и предлагает research снова, несмотря на правила в промпте
        Ai::fakeAgent(PlannerAgent::class, [
            [
                'result' => json_encode([
                    'next_step' => ['agent' => 'research', 'task' => 'еще раз поищи'],
                    'thought' => 'мне мало данных'
                ])
            ]
        ]);

        $planner = new DynamicPlanner();

        $nextStep = $planner->nextStep($state);

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
            step: new OrchestrationStep('summary', 'Подведи итог'),
            context: 'Вот твой отчет.',
            history: [
                [
                    'agent' => 'research',
                    'task' => 'Поиск информации о алгоритме формирования хеша пароля HASHPASS',
                    'result' => 'Данные найдены.'
                ],
                [
                    'agent' => 'summary',
                    'task' => 'Подведи итог',
                    'result' => 'Вот твой отчет.'
                ]
            ]
        );

        $planner = new DynamicPlanner();
        $nextStep = $planner->nextStep($state);

        // Должен вернуть null
        $this->assertNull($nextStep, "Planner should return null after summary agent has finished");
    }

    public function test_planner_switches_to_summary_on_empty_results()
    {
        // Создаем состояние с "Knowledge base is empty" в истории
        $state = new AgentState(
            input: 'Поиск информации о HASHPASS',
            step: new OrchestrationStep('research', 'Поиск'),
            context: 'Knowledge base is empty.',
            history: [
                [
                    'agent' => 'research',
                    'task' => 'Поиск',
                    'result' => 'Knowledge base is empty.'
                ]
            ]
        );

        $planner = new DynamicPlanner();
        $nextStep = $planner->nextStep($state);

        // Должен переключиться на summary без вызова LLM
        $this->assertNotNull($nextStep);
        $this->assertEquals('summary', $nextStep?->agent);
        $this->assertStringContainsString('информация не найдена', $nextStep?->task);
    }
}
