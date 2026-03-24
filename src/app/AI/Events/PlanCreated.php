<?php

namespace App\AI\Events;

use App\AI\DTO\Plan;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PlanCreated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Plan $plan,
        public array $context = []
    ) {}
}
