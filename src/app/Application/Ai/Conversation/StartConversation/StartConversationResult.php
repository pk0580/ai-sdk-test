<?php

declare(strict_types=1);

namespace App\Application\Ai\Conversation\StartConversation;

use App\Domain\Ai\Conversation\Conversation;

final readonly class StartConversationResult
{
    public function __construct(public Conversation $conversation) {}

    public function sessionId(): string
    {
        return $this->conversation->sessionId->value;
    }

    public function input(): string
    {
        return $this->conversation->input;
    }
}
