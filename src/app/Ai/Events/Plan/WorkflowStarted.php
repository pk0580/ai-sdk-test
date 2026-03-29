<?php

namespace App\Ai\Events\Plan;

use App\Ai\Core\Plans\OrchestrationStep;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class WorkflowStarted
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public OrchestrationStep $step,
        public string $input
    ) {}
}
