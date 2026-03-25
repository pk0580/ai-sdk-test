<?php

namespace App\Ai\Listeners;

use App\Ai\Events\ToolResultReceived;
use App\Ai\Events\ReflectionGenerated;
use App\Ai\Core\Reflector;
use Illuminate\Support\Facades\Log;

class ReflectListener
{
    private Reflector $reflector;

    public function __construct(Reflector $reflector)
    {
        $this->reflector = $reflector;
    }

    public function handle(ToolResultReceived $event): void
    {
        Log::info("ИИ: Получен результат работы инструмента, осмысление...", ['tool' => $event->step->tool, 'result' => $event->result]);

        $userMessage = $event->context['query'] ?? 'No user query in context';

        $analysis = $this->reflector->reflect($userMessage, $event->step, $event->result);

        ReflectionGenerated::dispatch(
            $analysis['decision'],
            $analysis['thought'],
            array_merge($event->context, ['next_suggestion' => $analysis['next_suggestion'] ?? null])
        );
    }
}
