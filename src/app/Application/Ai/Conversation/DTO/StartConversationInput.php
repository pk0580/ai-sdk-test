<?php

declare(strict_types=1);

namespace App\Application\Ai\Conversation\DTO;

final readonly class StartConversationInput
{
    public function __construct(
        public string  $message,
        public ?string $sessionId = null,
    ) {}
}
