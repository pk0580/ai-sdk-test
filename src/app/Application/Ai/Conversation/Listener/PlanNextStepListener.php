<?php

declare(strict_types=1);

namespace App\Application\Ai\Conversation\Listener;

use App\Application\Ai\Conversation\Event\StepRequested;
use App\Application\Ai\Conversation\UseCase\PlanNextStepUseCase;

final readonly class PlanNextStepListener
{
    public function __construct(private PlanNextStepUseCase $useCase) {}

    public function handle(StepRequested $event): void
    {
        $this->useCase->execute($event->conversation);
    }
}
