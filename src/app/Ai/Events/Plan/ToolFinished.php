<?php

namespace App\Ai\Events\Plan;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ToolFinished
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public string $tool,
        public mixed $result,
        public ?string $agent = null
    ) {}
}
