<?php

namespace App\AI\Listeners;

use App\AI\Events\ReflectionGenerated;
use App\AI\Events\StepCompleted;
use Illuminate\Support\Facades\Log;

class LoopListener
{
    public function handle(ReflectionGenerated $event): void
    {
        Log::info("ИИ: Решение цикла: {$event->decision}", ['thought' => $event->thought]);

        if ($event->decision === 'finish') {
            Log::info("ИИ: Процесс завершен.");
            // Здесь можно бросить финальное событие или завершить процесс
        } else {
            Log::info("ИИ: Нужно перейти к следующему шагу (еще не реализовано).");
            // В реализации Шага 6 LoopController будет решать, какой следующий шаг
        }
    }
}
