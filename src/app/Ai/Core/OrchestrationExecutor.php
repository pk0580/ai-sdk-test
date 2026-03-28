<?php

namespace App\Ai\Core;

use App\Ai\Core\Plans\OrchestrationPlan;
use App\Ai\Core\State\AgentState;
use App\Ai\Events\Plan\StepFinished;
use App\Ai\Events\Plan\StepStarted;
use Exception;

class OrchestrationExecutor
{
    /**
     * @param array<string, mixed> $agents
     */
    public function __construct(private array $agents) {}

    public function execute(OrchestrationPlan $plan, string $initialInput): string
    {
        $state = new AgentState($initialInput);

        foreach ($plan->steps as $step) {
            if (!isset($this->agents[$step->agent])) {
                throw new Exception("Unknown agent: {$step->agent}");
            }

            StepStarted::dispatch($step);

            $agent = $this->agents[$step->agent];

            // Агент может принимать как строку, так и AgentState.
            // Для совместимости и расширяемости, мы можем передавать данные.
            // Если в шаге указана конкретная задача, используем её, иначе контекст от прошлого шага.
            $task = $step->task ?: $state->context ?: $state->input;

            $result = $agent->execute($task);

            $state->context = $result;
            $state->data[$step->agent] = $result;

            StepFinished::dispatch($step, $state);
        }

        return $state->context ?? '';
    }
}
