<?php

namespace App\Ai\Core;

use App\Ai\Core\State\AgentState;
use App\Ai\Events\Workflow\StepRequested;
use App\Ai\Events\Workflow\WorkflowStarted;
use Illuminate\Support\Facades\Log;

class Supervisor
{
    /**
     * @param string $userMessage
     * @return AgentState
     */
    public function handle(string $userMessage): AgentState
    {
        Log::info("Supervisor: Запуск Workflow", ['message' => $userMessage]);

        $state = AgentState::init($userMessage);

        WorkflowStarted::dispatch($state);
        StepRequested::dispatch($state);

        return $state;
    }
}
