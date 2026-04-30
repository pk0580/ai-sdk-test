<?php

namespace Tests\Unit;

use App\Domain\Ai\Conversation\AgentName;
use App\Domain\Ai\Conversation\Conversation;
use App\Domain\Ai\Conversation\HistoryEntry;
use App\Infrastructure\Ai\Agent\PlannerAgent;
use App\Infrastructure\Ai\Planner\LlmDynamicPlanner;
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
        $state = Conversation::start('Поиск информации о HASHPASS');
        $state = $state->withStepResult(
            new \App\Domain\Ai\Conversation\PlanStep(AgentName::RESEARCH, 'Поиск информации о алгоритме формирования хеша пароля HASHPASS'),
            '[RESEARCH_FINISHED] Алгоритм формирования хеша пароля HASHPASS включает использование SALT для вычисления HMAC-SHA256.',
            true
        );

        // Настройка LlmDynamicPlanner через PlannerAgent fake
        Ai::fakeAgent(PlannerAgent::class, [
            [
                'result' => json_encode([
                    'next_step' => ['agent' => 'research', 'task' => 'еще раз поищи'],
                    'thought' => 'мне мало данных'
                ])
            ]
        ]);

        $planner = new LlmDynamicPlanner(app());

        $nextStep = $planner->decideNextStep($state);

        // Теперь LlmDynamicPlanner ДОЛЖЕН перехватить это и вернуть summary
        $this->assertNotNull($nextStep);
        $this->assertEquals(AgentName::SUMMARY, $nextStep?->agent, "Planner should have changed research to summary because of [RESEARCH_FINISHED] tag");
        $this->assertStringContainsString('Подведи итог', $nextStep?->task);
    }

    public function test_planner_stops_after_summary()
    {
        // Создаем состояние, где summary уже есть в истории
        $state = Conversation::start('Поиск информации о HASHPASS');
        $state = $state->withStepResult(
            new \App\Domain\Ai\Conversation\PlanStep(AgentName::RESEARCH, 'Поиск информации о алгоритме формирования хеша пароля HASHPASS'),
            'Данные найдены.',
            true
        );
        $state = $state->withStepResult(
            new \App\Domain\Ai\Conversation\PlanStep(AgentName::SUMMARY, 'Подведи итог'),
            'Вот твой отчет.',
            true
        );

        $planner = new LlmDynamicPlanner(app());
        $nextStep = $planner->decideNextStep($state);

        $this->assertNull($nextStep, "Planner should return null after summary agent has finished");
    }

    public function test_planner_switches_to_summary_on_too_much_history()
    {
        // Создаем состояние с длинной историей
        $state = Conversation::start('Поиск информации о HASHPASS');
        for ($i = 1; $i <= LlmDynamicPlanner::HISTORY_LIMIT; $i++) {
            $state = $state->withStepResult(
                new \App\Domain\Ai\Conversation\PlanStep(AgentName::RESEARCH, "Поиск $i"),
                "Данные $i",
                true
            );
        }

        $planner = new LlmDynamicPlanner(app());
        $nextStep = $planner->decideNextStep($state);

        // Должен переключиться на summary (history count >= limit)
        $this->assertNotNull($nextStep);
        $this->assertEquals(AgentName::SUMMARY, $nextStep?->agent);
    }
}
