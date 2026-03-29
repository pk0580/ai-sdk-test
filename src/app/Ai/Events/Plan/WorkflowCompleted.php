<?php

namespace App\Ai\Events\Plan;

use App\Ai\Core\State\AgentState;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class WorkflowCompleted
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public AgentState $state
    ) {}
}
