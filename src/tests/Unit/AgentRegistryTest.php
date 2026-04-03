<?php

namespace Tests\Unit;

use App\Ai\Agents\ResearchAgent;
use App\Ai\Agents\SummaryAgent;
use App\Ai\Core\AgentRegistry;
use Tests\TestCase;
use InvalidArgumentException;

class AgentRegistryTest extends TestCase
{
    public function test_can_get_agents_from_registry()
    {
        $research = $this->createMock(ResearchAgent::class);
        $summary = $this->createMock(SummaryAgent::class);

        $registry = new AgentRegistry($research, $summary);

        $this->assertSame($research, $registry->get('research'));
        $this->assertSame($summary, $registry->get('summary'));
    }

    public function test_throws_exception_for_unknown_agent()
    {
        $research = $this->createMock(ResearchAgent::class);
        $summary = $this->createMock(SummaryAgent::class);

        $registry = new AgentRegistry($research, $summary);

        $this->expectException(InvalidArgumentException::class);
        $registry->get('unknown');
    }
}
