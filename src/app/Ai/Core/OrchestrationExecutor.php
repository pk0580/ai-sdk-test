<?php

namespace App\Ai\Core;

use App\Ai\Core\State\AgentState;
use App\Ai\Events\Plan\StepCompleted;
use App\Ai\Events\Plan\StepStarted;
use Exception;

readonly class OrchestrationExecutor
{
    /**
     * @param array<string, mixed> $agents
     */
    public function __construct(private array $agents) {}

    /**
     * @throws Exception
     */
    public function runStep(AgentState $state): void
    {
        if (!$step = $state->step) {
            return;
        }

        if (!isset($this->agents[$step->agent])) {
            throw new Exception("Unknown agent: {$step->agent}");
        }

        StepStarted::dispatch($step, $state);

        $agent = $this->agents[$step->agent];

        $result = $agent->execute($state);

        $state->context = $result;
        $state->history[] = [
            'agent' => $step->agent,
            'task' => $step->task,
            'result' => $result
        ];

        StepCompleted::dispatch($step, $state);
    }
}
