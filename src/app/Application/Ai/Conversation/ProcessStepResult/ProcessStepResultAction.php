<?php

declare(strict_types=1);

namespace App\Application\Ai\Conversation\ProcessStepResult;

use App\Application\Ai\Conversation\Event\StepRequested;
use App\Application\Ai\Conversation\Event\WorkflowCompleted;
use App\Domain\Ai\Conversation\Conversation;
use App\Domain\Ai\Conversation\PlanStep;
use Illuminate\Contracts\Events\Dispatcher;

final readonly class ProcessStepResultAction
{
    public function __construct(private Dispatcher $events) {}

    public function handle(
        Conversation $conversation,
        PlanStep $step,
        string $result,
        bool $success,
    ): void {
        $next = $conversation->withStepResult($step, $result, $success);

        if ($next->reachedHistoryLimit() || $next->isFinished()) {
            $this->events->dispatch(new WorkflowCompleted($next));
            return;
        }

        $this->events->dispatch(new StepRequested($next));
    }
}
