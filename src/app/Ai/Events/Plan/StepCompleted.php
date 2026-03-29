<?php

namespace App\Ai\Events\Plan;

use App\Ai\Core\Plans\OrchestrationStep;
use App\Ai\Core\State\AgentState;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class StepCompleted
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public OrchestrationStep $step,
        public AgentState $state
    ) {}
}
