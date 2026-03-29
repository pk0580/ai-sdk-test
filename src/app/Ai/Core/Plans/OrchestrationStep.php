<?php

namespace App\Ai\Core\Plans;

class OrchestrationStep
{
    public function __construct(
        public string $agent,
        public string $task,
        public string $id = '',
        public array $next = []
    ) {}
}
