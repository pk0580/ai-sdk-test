<?php

namespace App\AI\Listeners;

use App\AI\Events\UserMessageReceived;
use App\AI\Events\PlanCreated;
use App\AI\Core\Planner;
use Illuminate\Support\Facades\Log;

class PlanListener
{
    public function __construct(protected Planner $planner) {}

    public function handle(UserMessageReceived $event): void
    {
        Log::info("ИИ: Получено сообщение от пользователя, создание плана...", ['message' => $event->message]);

        $plan = $this->planner->generate($event->message);

        PlanCreated::dispatch($plan, $event->context);
    }
}
