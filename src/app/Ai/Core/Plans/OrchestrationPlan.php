<?php

namespace App\Ai\Core\Plans;

class OrchestrationPlan
{
    /**
     * @param OrchestrationStep[] $steps
     */
    public function __construct(
        public array $steps
    ) {}
}
