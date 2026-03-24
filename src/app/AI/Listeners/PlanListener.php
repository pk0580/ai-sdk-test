<?php

namespace App\AI\Listeners;

use App\AI\Events\UserMessageReceived;
use App\AI\Events\PlanCreated;
use App\AI\DTO\Plan;
use Illuminate\Support\Facades\Log;

class PlanListener
{
    public function handle(UserMessageReceived $event): void
    {
        Log::info("AI: User message received, creating plan...", ['message' => $event->message]);

        // В реальном приложении здесь будет вызов LLM Planner
        // Для демонстрации шага 1 создаем пустой или заглушечный план
        $plan = new Plan([
            ['tool' => 'example_tool', 'description' => 'Just a placeholder']
        ]);

        PlanCreated::dispatch($plan, $event->context);
    }
}
