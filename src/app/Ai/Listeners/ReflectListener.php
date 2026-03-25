<?php

namespace App\Ai\Listeners;

use App\Ai\Events\ToolResultReceived;
use App\Ai\Events\ReflectionGenerated;
use Illuminate\Support\Facades\Log;

class ReflectListener
{
    public function handle(ToolResultReceived $event): void
    {
        Log::info("ИИ: Получен результат работы инструмента, осмысление...", ['tool' => $event->step->tool, 'result' => $event->result]);

        // В реальности здесь LLM анализирует результат
        $decision = 'finish'; // Завершаем для простоты
        $thought = "Я получил результат от инструмента {$event->step->tool}. Теперь я могу закончить.";

        ReflectionGenerated::dispatch($decision, $thought, $event->context);
    }
}
