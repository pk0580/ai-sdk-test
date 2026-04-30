<?php

declare(strict_types=1);

namespace App\Application\Ai\Conversation\DTO;

final readonly class CancelConversationInput
{
    public function __construct(public string $sessionId) {}
}
