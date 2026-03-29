<?php

namespace App\Ai\Core;

use App\Ai\Agents\ResearchAgent;
use App\Ai\Agents\SummaryAgent;
use App\Ai\Core\Interfaces\DynamicPlannerInterface;
use App\Ai\Core\Plans\OrchestrationStep;
use App\Ai\Core\State\AgentState;
use App\Ai\Events\Plan\WorkflowCompleted;
use App\Ai\Events\Plan\WorkflowStarted;
use Exception;
use Illuminate\Support\Facades\Log;

class Supervisor
{
    private array $agents = [];
    private OrchestrationExecutor $executor;

    public function __construct(
        ResearchAgent $researchAgent,
        SummaryAgent $summaryAgent,
        private readonly DynamicPlannerInterface $planner
    ) {
        $this->agents['research'] = $researchAgent;
        $this->agents['summary'] = $summaryAgent;

        $this->executor = new OrchestrationExecutor($this->agents);
    }

    /**
     * @throws Exception
     */
    public function handle(string $userMessage): AgentState
    {
        Log::info("Supervisor: Получен запрос", ['message' => $userMessage]);

        $initialStep = $this->planner->initialStep($userMessage);

        $state = new AgentState(
            input: $userMessage,
            step: $initialStep,
        );

        WorkflowStarted::dispatch($initialStep, $userMessage);

        $this->runCycle($state);

        return $state;
    }

    /**
     * Запускает цикл: выполнение шага -> планирование следующего -> выполнение...
     * @throws Exception
     */
    private function runCycle(AgentState $state): void
    {
        while ($state->step) {
            Log::info("Supervisor: Выполнение шага", ['agent' => $state->step->agent]);

            $this->executor->runStep($state);

            // После завершения шага спрашиваем планировщик, что дальше
            $nextStep = $this->planner->nextStep($state);

            if (!$nextStep) {
                Log::info("Supervisor: Планировщик решил завершить выполнение");
                $state->step = null;
                break;
            }

            Log::info("Supervisor: Добавлен новый шаг", [
                'agent' => $nextStep->agent,
                'task' => $nextStep->task
            ]);

            $state->step = $nextStep;
        }

        WorkflowCompleted::dispatch($state);
    }
}
