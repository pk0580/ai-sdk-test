<?php

namespace App\AI\Listeners;

use App\AI\Events\ToolCalled;
use App\AI\Events\ToolResultReceived;
use Illuminate\Support\Facades\Log;

class ExecuteToolListener
{
    public function handle(ToolCalled $event): void
    {
        Log::info("AI: Executing tool...", ['tool' => $event->step->tool]);

        // Логика вызова инструмента будет в Шаге 3
        // Пока просто возвращаем заглушку
        $result = "Result of {$event->step->tool}";

        ToolResultReceived::dispatch($event->step, $result, $event->context);
    }
}
