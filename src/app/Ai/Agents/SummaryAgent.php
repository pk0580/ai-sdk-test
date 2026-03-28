<?php

namespace App\Ai\Agents;

use App\Ai\Core\State\AgentState;
use Illuminate\Support\Facades\Log;

use Laravel\Ai\Attributes\MaxTokens;
use Laravel\Ai\Attributes\Timeout;
// use Laravel\Ai\Attributes\UseSmartestModel;

// #[UseSmartestModel]
#[MaxTokens(2000)]
#[Timeout(300)]
class SummaryAgent extends BaseAgent
{
    public function __construct()
    {
        $prompt = "Ты — Summary Agent. Твоя задача — свести все собранные данные в краткий, понятный и структурированный отчет.
        Выделяй ключевые моменты и рекомендации.";

        parent::__construct('SummaryAgent', $prompt);
    }

    public function model(): string
    {
        $defaultProvider = config('ai.default');
        return config("ai.providers.{$defaultProvider}.models.text.smartest")
            ?? config("ai.providers.{$defaultProvider}.model")
            ?? 'gpt-4o';
    }

    public function execute(string|AgentState $task): string
    {
        $input = ($task instanceof AgentState) ? ($task->context ?: $task->input) : $task;
        Log::info("SummaryAgent: Создание саммари", ['task_input' => $input]);

        // SummaryAgent может просто отвечать напрямую, так как его задача - синтез
        return $this->ask("Создай резюме на основе следующих данных: " . $input);
    }
}
