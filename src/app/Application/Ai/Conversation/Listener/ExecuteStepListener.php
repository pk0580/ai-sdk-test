<?php

declare(strict_types=1);

namespace App\Application\Ai\Conversation\Listener;

use App\Application\Ai\Conversation\Event\StepPlanned;
use App\Application\Ai\Conversation\ExecuteStep\ExecuteStepAction;

final readonly class ExecuteStepListener
{
    public function __construct(private ExecuteStepAction $action) {}

    public function handle(StepPlanned $event): void
    {
        $this->action->handle($event->conversation, $event->step);
    }
}
