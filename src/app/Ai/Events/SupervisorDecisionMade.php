<?php

namespace App\Ai\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SupervisorDecisionMade
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public array $decision,
        public string $userMessage
    ) {}
}
