<?php

namespace App\Ai\Listeners\Workflow;

use App\Ai\Core\Interfaces\DynamicPlannerInterface;
use App\Ai\Events\Workflow\StepPlanned;
use App\Ai\Events\Workflow\StepRequested;
use App\Ai\Events\Workflow\WorkflowCompleted;

class PlanNextStepListener
{
    public function __construct(
        private DynamicPlannerInterface $planner
    ) {}

    public function handle(StepRequested $event): void
    {
        $state = $event->state;

        $step = $this->planner->decideNextStep($state);

        if (!$step) {
            WorkflowCompleted::dispatch($state);
            return;
        }

        StepPlanned::dispatch($state, $step);
    }
}
