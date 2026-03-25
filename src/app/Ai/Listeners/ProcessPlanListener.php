<?php

namespace App\Ai\Listeners;

use App\Ai\Events\PlanCreated;
use App\Ai\Events\ToolCalled;
use Illuminate\Support\Facades\Log;

class ProcessPlanListener
{
    public function handle(PlanCreated $event): void
    {
        $plan = $event->plan;

        Log::info("ИИ: План создан, всего шагов: " . $plan->steps->count());

        if ($plan->steps->isNotEmpty()) {
            $firstStep = $plan->steps->first();
            Log::info("ИИ: Запуск первого шага: {$firstStep->tool}");
            ToolCalled::dispatch($firstStep, $event->context);
        } else {
            Log::warning("ИИ: План пуст.");
        }
    }
}
