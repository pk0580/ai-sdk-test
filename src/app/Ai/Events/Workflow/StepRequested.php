<?php

namespace App\Ai\Events\Workflow;

use App\Ai\Core\State\AgentState;
use Illuminate\Foundation\Events\Dispatchable;

class StepRequested
{
    use Dispatchable;

    public function __construct(public AgentState $state) {}
}
