<?php

declare(strict_types=1);

namespace App\Application\Ai\Conversation\StartConversation;

final readonly class StartConversationData
{
    public function __construct(
        public string  $message,
        public ?string $sessionId = null,
    ) {}
}
