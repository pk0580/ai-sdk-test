<?php

declare(strict_types=1);

namespace App\Application\Ai\Conversation\Listener;

use App\Application\Ai\Conversation\Event\StepPlanned;
use App\Application\Ai\Conversation\UseCase\ExecuteStepUseCase;

final readonly class ExecuteStepListener
{
    public function __construct(private ExecuteStepUseCase $useCase) {}

    public function handle(StepPlanned $event): void
    {
        $this->useCase->execute($event->conversation, $event->step);
    }
}
