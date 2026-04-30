<?php

namespace Tests\Unit;

use App\Domain\Ai\Conversation\AgentName;
use App\Infrastructure\Ai\Agent\ResearchAgent;
use App\Infrastructure\Ai\Agent\SummaryAgent;
use App\Infrastructure\Ai\Executor\AgentRegistryExecutor;
use Tests\TestCase;
use InvalidArgumentException;

class AgentRegistryTest extends TestCase
{
    public function test_can_get_agents_from_registry()
    {
        $research = $this->createMock(ResearchAgent::class);
        $summary = $this->createMock(SummaryAgent::class);

        $registry = new AgentRegistryExecutor($research, $summary);

        $this->assertSame($research, $registry->get(AgentName::RESEARCH));
        $this->assertSame($summary, $registry->get(AgentName::SUMMARY));
    }

    public function test_throws_exception_for_unknown_agent()
    {
        $research = $this->createMock(ResearchAgent::class);
        $summary = $this->createMock(SummaryAgent::class);

        $registry = new AgentRegistryExecutor($research, $summary);

        $this->expectException(InvalidArgumentException::class);
        $registry->get('unknown');
    }
}
