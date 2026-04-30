<?php

declare(strict_types=1);

namespace App\Domain\Ai\Conversation;

final readonly class PlanStep
{
    public string $agent;
    public string $task;

    public function __construct(string|AgentName $agent, string $task)
    {
        $this->agent = $agent instanceof AgentName ? $agent->value : $agent;
        $this->task = $task;
    }

    public function agentName(): AgentName
    {
        return AgentName::fromString($this->agent);
    }
}
