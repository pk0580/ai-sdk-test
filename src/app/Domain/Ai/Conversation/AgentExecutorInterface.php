<?php

declare(strict_types=1);

namespace App\Domain\Ai\Conversation;

interface AgentExecutorInterface
{
    /**
     * Run the agent identified by $step against the given conversation
     * context and return a textual result.
     */
    public function execute(PlanStep $step, Conversation $conversation): string;
}
