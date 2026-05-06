<?php

declare(strict_types=1);

namespace App\Application\Ai\Conversation\CancelConversation;

use App\Domain\Ai\Conversation\ConversationCancellationInterface;
use App\Domain\Ai\Conversation\SessionId;

final readonly class CancelConversationAction
{
    public function __construct(
        private ConversationCancellationInterface $cancellation,
    ) {}

    public function handle(CancelConversationData $input): void
    {
        $this->cancellation->cancel(SessionId::fromString($input->sessionId));
    }
}
