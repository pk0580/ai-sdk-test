<?php

namespace App\Ai\Core\Plans;

class Step
{
    public function __construct(
        public string $agent,
        public string $task
    ) {}
}
