<?php

declare(strict_types=1);

namespace App\Application\Ai\Conversation\StartConversation;

use App\Application\Ai\Conversation\Event\StepRequested;
use App\Application\Ai\Conversation\Event\WorkflowStarted;
use App\Domain\Ai\Conversation\Conversation;
use App\Domain\Ai\Conversation\SessionId;
use Illuminate\Contracts\Events\Dispatcher;

final readonly class StartConversationAction
{
    public function __construct(private Dispatcher $events) {}

    public function handle(StartConversationData $input): StartConversationResult
    {
        $sessionId = $input->sessionId !== null && $input->sessionId !== ''
            ? SessionId::fromString($input->sessionId)
            : null;

        $conversation = Conversation::start($input->message, $sessionId);

        $this->events->dispatch(new WorkflowStarted($conversation));
        $this->events->dispatch(new StepRequested($conversation));

        return new StartConversationResult($conversation);
    }
}
