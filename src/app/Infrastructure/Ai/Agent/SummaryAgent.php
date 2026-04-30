<?php

declare(strict_types=1);

namespace App\Infrastructure\Ai\Agent;

use App\Domain\Ai\Conversation\AgentName;
use App\Domain\Ai\Conversation\Conversation;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Attributes\MaxTokens;
use Laravel\Ai\Attributes\Timeout;

#[MaxTokens(2000)]
#[Timeout(300)]
class SummaryAgent extends BaseAgent
{
    public function __construct()
    {
        parent::__construct(
            name: 'SummaryAgent',
            systemPrompt: "Ты — Summary Agent. Твоя задача — свести все собранные данные в краткий, понятный и структурированный отчет.
        Выделяй ключевые моменты и рекомендации.

        ОГРАНИЧЕНИЯ:
        - ТЫ ДОЛЖЕН ОТВЕЧАТЬ ТОЛЬКО НА ОСНОВЕ ПРЕДОСТАВЛЕННЫХ ДАННЫХ.
        - ЕСЛИ ДАННЫХ НЕТ (база пуста или информация не найдена), ТАК И НАПИШИ: 'Информация не найдена в базе знаний'.
        - ЗАПРЕЩЕНО выдумывать информацию о погоде, Китае или других темах, если их нет в собранных данных.",
        );
    }

    public function model(): string
    {
        $defaultProvider = config('ai.default');

        return config("ai.providers.{$defaultProvider}.models.text.smartest")
            ?? config("ai.providers.{$defaultProvider}.model")
            ?? 'gpt-4o';
    }

    public function execute(string $task, Conversation $conversation): string
    {
        $input = "Исходный вопрос: " . $conversation->input . "\n\n";
        $input .= "Собранные данные:\n";

        foreach ($conversation->history as $entry) {
            if ($entry->agent === AgentName::RESEARCH && $entry->success) {
                $input .= '- ' . $entry->result . "\n";
            }
        }

        Log::info('SummaryAgent: building summary', ['input_length' => strlen($input)]);

        return $this->ask("Создай структурированный отчет на основе следующих данных: \n\n" . $input . "\n\nЗадание: " . $task);
    }
}
