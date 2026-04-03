<?php

namespace Tests\Unit;

use App\Ai\Core\Plans\Step;
use App\Ai\Core\State\AgentState;
use App\Ai\Events\Workflow\StepRequested;
use App\Ai\Events\Workflow\WorkflowStarted;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;
use App\Ai\Core\Supervisor;
use Mockery;

class MultiAgentTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_supervisor_dispatches_initial_events()
    {
        Event::fake();

        $supervisor = new Supervisor();
        $state = $supervisor->handle("Simple task");

        $this->assertInstanceOf(AgentState::class, $state);
        $this->assertEquals("Simple task", $state->input);

        Event::assertDispatched(WorkflowStarted::class, function ($event) use ($state) {
            return $event->state->input === $state->input;
        });

        Event::assertDispatched(StepRequested::class, function ($event) use ($state) {
            return $event->state->input === $state->input;
        });
    }
}
