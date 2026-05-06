<?php

declare(strict_types=1);

namespace App\Application\Ai\Conversation\Listener;

use App\Application\Ai\Conversation\Event\StepRequested;
use App\Application\Ai\Conversation\PlanNextStep\PlanNextStepAction;

final readonly class PlanNextStepListener
{
    public function __construct(private PlanNextStepAction $action) {}

    public function handle(StepRequested $event): void
    {
        $this->action->handle($event->conversation);
    }
}
