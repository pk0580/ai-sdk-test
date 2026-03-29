<?php

namespace App\Ai\Core\State;

use App\Ai\Core\Plans\OrchestrationStep;

class AgentState
{
    public function __construct(
        public string $input,
        public ?OrchestrationStep $step = null,
        public ?string $context = null,
        public array $history = [],
    ) {}
}
