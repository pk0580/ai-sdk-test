<?php

namespace App\AI\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ReflectionGenerated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public string $decision, // e.g., 'continue', 'finish'
        public ?string $thought = null,
        public array $context = []
    ) {}
}
