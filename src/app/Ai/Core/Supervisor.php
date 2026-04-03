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
     * @param string|null $sessionId
     * @return AgentState
     */
    public function handle(string $userMessage, ?string $sessionId = null): AgentState
    {
        Log::info("Supervisor: Запуск Workflow", ['message' => $userMessage, 'session_id' => $sessionId]);

        $state = AgentState::init($userMessage, $sessionId);

        WorkflowStarted::dispatch($state);
        StepRequested::dispatch($state);

        return $state;
    }
}
