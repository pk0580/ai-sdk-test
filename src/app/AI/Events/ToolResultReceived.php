<?php

namespace App\AI\Events;

use App\AI\DTO\Step;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ToolResultReceived
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Step $step,
        public mixed $result,
        public array $context = []
    ) {}
}
