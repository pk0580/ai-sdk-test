<?php

namespace App\Ai\Listeners\Workflow;

use App\Ai\Events\Workflow\StepCompleted;
use App\Ai\Events\Workflow\StepRequested;
use App\Ai\Events\Workflow\WorkflowCompleted;

class UpdateStateListener
{
    public function handle(StepCompleted $event): void
    {
        $state = $event->state->withStepResult(
            step: $event->step,
            result: $event->result,
            success: $event->success
        );

        // Защита от бесконечного цикла (макс. 10 шагов в истории)
        if (count($state->history) >= 10) {
            WorkflowCompleted::dispatch($state);
            return;
        }

        if ($state->isFinished()) {
            WorkflowCompleted::dispatch($state);
            return;
        }

        StepRequested::dispatch($state);
    }
}
