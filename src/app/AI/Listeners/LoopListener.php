<?php

namespace App\AI\Listeners;

use App\AI\Events\ReflectionGenerated;
use App\AI\Events\StepCompleted;
use Illuminate\Support\Facades\Log;

class LoopListener
{
    public function handle(ReflectionGenerated $event): void
    {
        Log::info("AI: Loop decision: {$event->decision}", ['thought' => $event->thought]);

        if ($event->decision === 'finish') {
            Log::info("AI: Flow finished.");
            // Здесь можно бросить финальное событие или завершить процесс
        } else {
            Log::info("AI: Should continue to next step (not implemented yet).");
            // В реализации Шага 6 LoopController будет решать, какой следующий шаг
        }
    }
}
