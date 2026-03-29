<?php

namespace App\Ai\Events\Plan;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ToolCalled
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public string $tool,
        public array $parameters,
        public ?string $agent = null
    ) {}
}
