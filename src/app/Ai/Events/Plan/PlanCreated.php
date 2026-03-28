<?php

namespace App\Ai\Events\Plan;

use App\Ai\Core\Plans\OrchestrationPlan;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PlanCreated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public OrchestrationPlan $plan) {}
}
