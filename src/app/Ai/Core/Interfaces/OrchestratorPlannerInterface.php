<?php

namespace App\Ai\Core\Interfaces;

use App\Ai\Core\Plans\OrchestrationPlan;

interface OrchestratorPlannerInterface
{
    public function plan(string $message): OrchestrationPlan;
}
