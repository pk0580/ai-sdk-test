<?php

declare(strict_types=1);

namespace App\Application\Ai\Conversation\PlanNextStep;

use App\Application\Ai\Conversation\Event\StepPlanned;
use App\Application\Ai\Conversation\Event\WorkflowCompleted;
use App\Domain\Ai\Conversation\Conversation;
use App\Domain\Ai\Conversation\ConversationCancellationInterface;
use App\Domain\Ai\Conversation\PlannerInterface;
use Illuminate\Contracts\Events\Dispatcher;

final readonly class PlanNextStepAction
{
    public function __construct(
        private PlannerInterface                  $planner,
        private ConversationCancellationInterface $cancellation,
        private Dispatcher                        $events,
    ) {}

    public function handle(Conversation $conversation): void
    {
        if ($this->cancellation->isCancelled($conversation->sessionId)) {
            $this->cancellation->clear($conversation->sessionId);
            $this->events->dispatch(new WorkflowCompleted($conversation));
            return;
        }

        $step = $this->planner->decideNextStep($conversation);

        if ($step === null) {
            $this->events->dispatch(new WorkflowCompleted($conversation));
            return;
        }

        $this->events->dispatch(new StepPlanned($conversation, $step));
    }
}
