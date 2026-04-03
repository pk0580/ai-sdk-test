<?php

namespace App\Ai\Core\State;

use App\Ai\Core\Plans\Step;

class AgentState
{
    public function __construct(
        public string $input,
        public ?Step $step = null,
        public ?string $context = null,
        public array $history = [],
    ) {}

    public static function init(string $input): self
    {
        return new self(input: $input);
    }

    public function withStepResult(Step $step, string $result, bool $success): self
    {
        $newHistory = $this->history;
        $newHistory[] = [
            'agent' => $step->agent,
            'task' => $step->task,
            'result' => $result,
            'success' => $success,
            'timestamp' => now()->toDateTimeString(),
        ];

        return new self(
            input: $this->input,
            step: null, // Очищаем текущий шаг, так как он завершен
            context: $this->context,
            history: $newHistory
        );
    }

    public function isFinished(): bool
    {
        // Логика завершения: например, если в последнем ответе есть [SUMMARY_FINISHED]
        // или если последний агент был summary и он был успешным.
        $lastEntry = end($this->history);
        if (!$lastEntry) return false;

        if ($lastEntry['agent'] === 'summary' && $lastEntry['success']) {
            return true;
        }

        if (str_contains($lastEntry['result'], '[FINISHED]')
            || str_contains($lastEntry['result'], '[SUMMARY_FINISHED]')
            || str_contains($lastEntry['result'], '[RESEARCH_FINISHED]')) {
            return true;
        }

        return false;
    }
}
