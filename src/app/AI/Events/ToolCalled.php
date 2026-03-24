<?php

namespace App\AI\Events;

use App\AI\DTO\Step;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ToolCalled
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Step $step,
        public array $context = []
    ) {}
}
