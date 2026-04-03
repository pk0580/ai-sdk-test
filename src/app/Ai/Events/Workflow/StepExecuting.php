<?php

namespace App\Ai\Events\Workflow;

use App\Ai\Core\Plans\Step;
use App\Ai\Core\State\AgentState;
use Illuminate\Foundation\Events\Dispatchable;

class StepExecuting
{
    use Dispatchable;

    public function __construct(
        public AgentState $state,
        public Step $step
    ) {}
}
