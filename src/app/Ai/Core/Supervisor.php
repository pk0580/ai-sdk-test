<?php

namespace App\Ai\Core;

use App\Ai\Agents\ResearchAgent;
use App\Ai\Agents\SummaryAgent;
use App\Ai\Core\Interfaces\OrchestratorPlannerInterface;
use App\Ai\Events\Plan\PlanCreated;
use Illuminate\Support\Facades\Log;

class Supervisor
{
    private array $agents = [];
    private OrchestrationExecutor $executor;

    public function __construct(
        ResearchAgent $researchAgent,
        SummaryAgent $summaryAgent,
        private OrchestratorPlannerInterface $planner
    ) {
        $this->agents['research'] = $researchAgent;
        $this->agents['summary'] = $summaryAgent;

        $this->executor = new OrchestrationExecutor($this->agents);
    }

    public function handle(string $userMessage): string
    {
        Log::info("Supervisor: Получен запрос", ['message' => $userMessage]);

        $plan = $this->planner->plan($userMessage);

        PlanCreated::dispatch($plan);

        Log::info("Supervisor: План создан", ['steps_count' => count($plan->steps)]);

        return $this->executor->execute($plan, $userMessage);
    }
}
