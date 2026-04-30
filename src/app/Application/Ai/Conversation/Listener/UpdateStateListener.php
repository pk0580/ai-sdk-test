<?php

declare(strict_types=1);

namespace App\Application\Ai\Conversation\Listener;

use App\Application\Ai\Conversation\Event\StepCompleted;
use App\Application\Ai\Conversation\UseCase\ProcessStepResultUseCase;

final readonly class UpdateStateListener
{
    public function __construct(private ProcessStepResultUseCase $useCase) {}

    public function handle(StepCompleted $event): void
    {
        $this->useCase->execute(
            conversation: $event->conversation,
            step: $event->step,
            result: $event->result,
            success: $event->success,
        );
    }
}
