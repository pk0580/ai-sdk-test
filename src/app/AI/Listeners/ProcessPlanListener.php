<?php

namespace App\AI\Listeners;

use App\AI\Events\PlanCreated;
use App\AI\Events\ToolCalled;
use Illuminate\Support\Facades\Log;

class ProcessPlanListener
{
    public function handle(PlanCreated $event): void
    {
        $plan = $event->plan;

        Log::info("AI: Plan created, total steps: " . $plan->steps->count());

        if ($plan->steps->isNotEmpty()) {
            $firstStep = $plan->steps->first();
            Log::info("AI: Starting first step: {$firstStep->tool}");
            ToolCalled::dispatch($firstStep, $event->context);
        } else {
            Log::warning("AI: Plan is empty.");
        }
    }
}
