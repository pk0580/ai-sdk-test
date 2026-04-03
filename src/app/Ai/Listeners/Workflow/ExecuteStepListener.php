<?php

namespace App\Ai\Listeners\Workflow;

use App\Ai\Core\AgentRegistry;
use App\Ai\Events\Workflow\StepCompleted;
use App\Ai\Events\Workflow\StepExecuting;
use App\Ai\Events\Workflow\StepPlanned;
use App\Ai\Events\Workflow\WorkflowCompleted;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class ExecuteStepListener
{
    public function __construct(
        private AgentRegistry $registry
    ) {}

    public function handle(StepPlanned $event): void
    {
        $state = $event->state;
        $step = $event->step;

        Log::info("ExecuteStepListener: Запуск агента [{$step->agent}] для задачи: {$step->task}");

        StepExecuting::dispatch($state, $step);

        try {
            $agent = $this->registry->get($step->agent);

            // Важно: Агенты теперь принимают task и state отдельно или только task
            // Но согласно описанию в ResearchAgent::execute(string $task, AgentState $state)
            $result = $agent->execute($step->task, $state);

            StepCompleted::dispatch(
                state: $state,
                step: $step,
                result: (string)$result,
                success: true
            );
        } catch (Throwable $e) {
            StepCompleted::dispatch(
                state: $state,
                step: $step,
                result: $e->getMessage(),
                success: false
            );
        }
    }
}
