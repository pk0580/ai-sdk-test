<?php

declare(strict_types=1);

namespace App\Application\Ai\Conversation\UseCase;

use App\Application\Ai\Conversation\DTO\CancelConversationInput;
use App\Domain\Ai\Conversation\ConversationCancellationInterface;
use App\Domain\Ai\Conversation\SessionId;

final readonly class CancelConversationUseCase
{
    public function __construct(
        private ConversationCancellationInterface $cancellation,
    ) {}

    public function execute(CancelConversationInput $input): void
    {
        $this->cancellation->cancel(SessionId::fromString($input->sessionId));
    }
}
