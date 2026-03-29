<?php

namespace App\Ai\Events;

use App\Ai\Core\Plans\OrchestrationStep;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SupervisorDecisionMade
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public OrchestrationStep|array $decision,
        public string                  $userMessage
    ) {}
}
