<?php

namespace App\Ai\Core\State;

class AgentState
{
    public function __construct(
        public string $input,
        public ?string $context = null,
        public array $data = []
    ) {}
}
