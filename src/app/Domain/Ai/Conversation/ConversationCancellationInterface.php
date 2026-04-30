<?php

declare(strict_types=1);

namespace App\Domain\Ai\Conversation;

interface ConversationCancellationInterface
{
    public function isCancelled(SessionId $sessionId): bool;

    public function cancel(SessionId $sessionId): void;

    public function clear(SessionId $sessionId): void;
}
