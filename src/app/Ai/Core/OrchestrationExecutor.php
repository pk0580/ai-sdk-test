<?php

namespace App\Ai\Core;

use App\Ai\Core\State\AgentState;
use App\Ai\Events\Plan\StepCompleted;
use App\Ai\Events\Plan\StepStarted;
use App\Ai\Utils\JsonSanitizer;
use Exception;
use Illuminate\Support\Facades\Log;

readonly class OrchestrationExecutor
{
    /**
     * @param array<string, mixed> $agents
     */
    public function __construct(private array $agents) {}

    /**
     * @throws Exception
     */
    public function runStep(AgentState $state): void
    {
        if (!$step = $state->step) {
            return;
        }

        if (!isset($this->agents[$step->agent])) {
            throw new Exception("Unknown agent: {$step->agent}");
        }

        StepStarted::dispatch($step, $state);

        $agent = $this->agents[$step->agent];

        try {
            $result = $agent->execute($state);

            if (is_string($result)) {
                $result = JsonSanitizer::sanitizeUtf8($result);
            } elseif (is_array($result)) {
                array_walk_recursive($result, function (&$item) {
                    if (is_string($item)) {
                        $item = JsonSanitizer::sanitizeUtf8($item);
                    }
                });
            }

            $state->context = $result;
            $state->history[] = [
                'agent' => $step->agent,
                'task' => $step->task,
                'result' => $result
            ];
        } catch (Exception $e) {
            Log::error("OrchestrationExecutor: Ошибка при выполнении агента", [
                'agent' => $step->agent,
                'error' => $e->getMessage()
            ]);

            $state->history[] = [
                'agent' => $step->agent,
                'task' => $step->task,
                'error' => $e->getMessage(),
                'status' => 'failed'
            ];
            $state->context = "Ошибка при выполнении {$step->agent}: " . $e->getMessage();
        }

        StepCompleted::dispatch($step, $state);
    }
}
