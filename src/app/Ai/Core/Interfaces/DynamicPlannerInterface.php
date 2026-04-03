<?php

namespace App\Ai\Core\Interfaces;

use App\Ai\Core\Plans\Step;
use App\Ai\Core\State\AgentState;

interface DynamicPlannerInterface
{
    /**
     * Решает какой шаг выполнить следующим.
     * Если возвращает null — выполнение завершено.
     */
    public function decideNextStep(AgentState $state): ?Step;
}
