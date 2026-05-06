<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Repositories;

use App\Domain\Ai\Logging\AiLog;
use App\Domain\Ai\Logging\AiLogRepositoryInterface;
use App\Infrastructure\Persistence\Eloquent\Models\AiLogModel;

final class EloquentAiLogRepository implements AiLogRepositoryInterface
{
    public function save(AiLog $log): void
    {
        AiLogModel::query()->create([
            'session_id' => $log->sessionId,
            'agent_name' => $log->agentName,
            'thought' => $log->thought,
            'action' => $log->action,
            'input' => $log->input,
            'output' => $log->output,
            'latency' => $log->latency,
        ]);
    }
}
