<?php

namespace App\AI\Listeners;

use App\AI\Events\ToolResultReceived;
use App\AI\Events\ReflectionGenerated;
use Illuminate\Support\Facades\Log;

class ReflectListener
{
    public function handle(ToolResultReceived $event): void
    {
        Log::info("AI: Tool result received, reflecting...", ['tool' => $event->step->tool, 'result' => $event->result]);

        // В реальности здесь LLM анализирует результат
        $decision = 'finish'; // Завершаем для простоты
        $thought = "I have the result from {$event->step->tool}. I can finish now.";

        ReflectionGenerated::dispatch($decision, $thought, $event->context);
    }
}
