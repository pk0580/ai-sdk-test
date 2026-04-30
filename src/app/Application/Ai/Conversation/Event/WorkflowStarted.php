<?php

declare(strict_types=1);

namespace App\Application\Ai\Conversation\Event;

use App\Domain\Ai\Conversation\Conversation;
use Illuminate\Foundation\Events\Dispatchable;

final class WorkflowStarted
{
    use Dispatchable;

    public function __construct(public readonly Conversation $conversation) {}
}
