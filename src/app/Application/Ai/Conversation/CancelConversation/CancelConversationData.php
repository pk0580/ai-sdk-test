<?php

declare(strict_types=1);

namespace App\Application\Ai\Conversation\CancelConversation;

final readonly class CancelConversationData
{
    public function __construct(public string $sessionId) {}
}
