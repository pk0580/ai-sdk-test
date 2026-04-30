<?php

declare(strict_types=1);

namespace App\Application\Ai\Conversation\Event;

use App\Domain\Ai\Conversation\Conversation;
use App\Domain\Ai\Conversation\PlanStep;
use Illuminate\Foundation\Events\Dispatchable;

final class StepCompleted
{
    use Dispatchable;

    public function __construct(
        public readonly Conversation $conversation,
        public readonly PlanStep $step,
        public readonly string $result,
        public readonly bool $success,
    ) {}
}
