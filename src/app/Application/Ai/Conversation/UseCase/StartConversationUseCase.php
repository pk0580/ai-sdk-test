<?php

declare(strict_types=1);

namespace App\Application\Ai\Conversation\UseCase;

use App\Application\Ai\Conversation\DTO\StartConversationInput;
use App\Application\Ai\Conversation\DTO\StartConversationOutput;
use App\Application\Ai\Conversation\Event\StepRequested;
use App\Application\Ai\Conversation\Event\WorkflowStarted;
use App\Domain\Ai\Conversation\Conversation;
use App\Domain\Ai\Conversation\SessionId;
use Illuminate\Contracts\Events\Dispatcher;

final readonly class StartConversationUseCase
{
    public function __construct(private Dispatcher $events) {}

    public function execute(StartConversationInput $input): StartConversationOutput
    {
        $sessionId = $input->sessionId !== null && $input->sessionId !== ''
            ? SessionId::fromString($input->sessionId)
            : null;

        $conversation = Conversation::start($input->message, $sessionId);

        $this->events->dispatch(new WorkflowStarted($conversation));
        $this->events->dispatch(new StepRequested($conversation));

        return new StartConversationOutput($conversation);
    }
}
