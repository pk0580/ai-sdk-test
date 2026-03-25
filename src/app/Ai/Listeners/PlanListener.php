<?php

namespace App\Ai\Listeners;

use App\Ai\Events\UserMessageReceived;
use App\Ai\Events\PlanCreated;
use App\Ai\Core\Planner;
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
