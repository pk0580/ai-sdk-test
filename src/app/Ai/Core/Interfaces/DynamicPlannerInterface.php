<?php

namespace App\Ai\Core\Interfaces;

use App\Ai\Core\Plans\OrchestrationStep;
use App\Ai\Core\State\AgentState;

interface DynamicPlannerInterface
{
    /**
     * Предлагает первый шаг для выполнения.
     */
    public function initialStep(string $message): OrchestrationStep;

    /**
     * Предлагает следующий шаг на основе текущего состояния.
     * Если возвращает null — выполнение завершено.
     */
    public function nextStep(AgentState $state): ?OrchestrationStep;
}
