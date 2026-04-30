<?php

namespace Tests\Unit;

use App\Application\Ai\Conversation\DTO\StartConversationInput;
use App\Application\Ai\Conversation\Event\StepRequested;
use App\Application\Ai\Conversation\Event\WorkflowStarted;
use App\Application\Ai\Conversation\UseCase\StartConversationUseCase;
use App\Domain\Ai\Conversation\Conversation;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;
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

        /** @var StartConversationUseCase $useCase */
        $useCase = app(StartConversationUseCase::class);
        $output = $useCase->execute(new StartConversationInput("Simple task"));

        $conversation = $output->conversation;
        $this->assertInstanceOf(Conversation::class, $conversation);
        $this->assertEquals("Simple task", $conversation->input);

        Event::assertDispatched(WorkflowStarted::class, function ($event) use ($conversation) {
            return $event->conversation->input === $conversation->input;
        });

        Event::assertDispatched(StepRequested::class, function ($event) use ($conversation) {
            return $event->conversation->input === $conversation->input;
        });
    }
}
