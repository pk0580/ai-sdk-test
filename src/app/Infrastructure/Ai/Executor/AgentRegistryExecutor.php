<?php

declare(strict_types=1);

namespace App\Infrastructure\Ai\Executor;

use App\Domain\Ai\Conversation\AgentExecutorInterface;
use App\Domain\Ai\Conversation\AgentName;
use App\Domain\Ai\Conversation\Conversation;
use App\Domain\Ai\Conversation\PlanStep;
use App\Infrastructure\Ai\Agent\BaseAgent;
use App\Infrastructure\Ai\Agent\ResearchAgent;
use App\Infrastructure\Ai\Agent\SummaryAgent;
use InvalidArgumentException;

final class AgentRegistryExecutor implements AgentExecutorInterface
{
    /** @var array<string, BaseAgent> */
    private array $agents;

    public function __construct(ResearchAgent $researchAgent, SummaryAgent $summaryAgent)
    {
        $this->agents = [
            AgentName::RESEARCH => $researchAgent,
            AgentName::SUMMARY => $summaryAgent,
        ];
    }

    public function execute(PlanStep $step, Conversation $conversation): string
    {
        $agent = $this->agents[$step->agent] ?? null;

        if ($agent === null) {
            throw new InvalidArgumentException("Agent [{$step->agent}] not found in registry.");
        }

        return $agent->execute($step->task, $conversation);
    }

    public function get(string $name): BaseAgent
    {
        if (!isset($this->agents[$name])) {
            throw new InvalidArgumentException("Agent [{$name}] not found in registry.");
        }

        return $this->agents[$name];
    }
}
