<?php

namespace App\Ai\Core;

use App\Ai\Agents\ResearchAgent;
use App\Ai\Agents\SummaryAgent;
use InvalidArgumentException;

class AgentRegistry
{
    private array $agents = [];

    public function __construct(
        ResearchAgent $researchAgent,
        SummaryAgent $summaryAgent
    ) {
        $this->agents['research'] = $researchAgent;
        $this->agents['summary'] = $summaryAgent;
    }

    public function get(string $name): mixed
    {
        if (!isset($this->agents[$name])) {
            throw new InvalidArgumentException("Agent [{$name}] not found in registry.");
        }

        return $this->agents[$name];
    }
}
