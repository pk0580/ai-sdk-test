<?php

namespace App\Ai\Events;

use App\Ai\DTO\Step;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class StepCompleted
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Step $step,
        public mixed $result = null,
        public array $context = []
    ) {}
}
