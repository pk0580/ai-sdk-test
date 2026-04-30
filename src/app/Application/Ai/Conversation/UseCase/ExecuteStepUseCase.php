<?php

declare(strict_types=1);

namespace App\Application\Ai\Conversation\UseCase;

use App\Application\Ai\Conversation\Event\StepCompleted;
use App\Application\Ai\Conversation\Event\StepExecuting;
use App\Domain\Ai\Conversation\AgentExecutorInterface;
use App\Domain\Ai\Conversation\Conversation;
use App\Domain\Ai\Conversation\PlanStep;
use Illuminate\Contracts\Events\Dispatcher;
use Throwable;

final readonly class ExecuteStepUseCase
{
    public function __construct(
        private AgentExecutorInterface $executor,
        private Dispatcher             $events,
    ) {}

    public function execute(Conversation $conversation, PlanStep $step): void
    {
        $this->events->dispatch(new StepExecuting($conversation, $step));

        try {
            $result = $this->executor->execute($step, $conversation);
            $this->events->dispatch(new StepCompleted($conversation, $step, $result, true));
        } catch (Throwable $e) {
            $this->events->dispatch(new StepCompleted($conversation, $step, $e->getMessage(), false));
        }
    }
}
