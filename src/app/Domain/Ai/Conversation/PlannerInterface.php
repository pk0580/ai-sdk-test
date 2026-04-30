<?php

declare(strict_types=1);

namespace App\Domain\Ai\Conversation;

interface PlannerInterface
{
    /**
     * Decide the next step in the conversation. Returning null means the
     * workflow is finished and no further steps are required.
     */
    public function decideNextStep(Conversation $conversation): ?PlanStep;
}
