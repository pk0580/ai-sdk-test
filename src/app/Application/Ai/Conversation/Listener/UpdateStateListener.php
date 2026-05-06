<?php

declare(strict_types=1);

namespace App\Application\Ai\Conversation\Listener;

use App\Application\Ai\Conversation\Event\StepCompleted;
use App\Application\Ai\Conversation\ProcessStepResult\ProcessStepResultAction;

final readonly class UpdateStateListener
{
    public function __construct(private ProcessStepResultAction $action) {}

    public function handle(StepCompleted $event): void
    {
        $this->action->handle(
            conversation: $event->conversation,
            step: $event->step,
            result: $event->result,
            success: $event->success,
        );
    }
}
