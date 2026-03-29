<?php

namespace App\Ai\Agents;

use App\Ai\Core\State\AgentState;
use App\Ai\Tools\ToolRegistry;
use App\Ai\Core\LoopController;
use Laravel\Ai\Attributes\MaxSteps;
use Laravel\Ai\Attributes\Timeout;
use Illuminate\Support\Facades\Log;

#[MaxSteps(10)]
#[Timeout(600)]
class ResearchAgent extends BaseAgent
{
    private LoopController $loopController;
    private ToolRegistry $toolRegistry;

    public function __construct(LoopController $loopController, ToolRegistry $toolRegistry)
    {
        $prompt = "Ты — Research Agent. Твоя задача — глубоко изучить заданную тему, используя доступные инструменты поиска.
        Твой результат должен содержать факты, цифры и ключевую информацию.";

        parent::__construct('ResearchAgent', $prompt);
        $this->loopController = $loopController;
        $this->toolRegistry = $toolRegistry;
    }

    public function tools(): iterable
    {
        return $this->toolRegistry->all();
    }

    public function execute(string|AgentState $task): string
    {
        if ($task instanceof AgentState) {
            $step = $task->step;
            $instruction = $step?->task ?: $task->input;

            $history = "";
            if (!empty($task->history)) {
                $history = "\n\nРанее было выполнено:\n";
                foreach ($task->history as $entry) {
                    $history .= "- Задача: {$entry['task']}\n  Результат: " . substr($entry['result'], 0, 200) . "...\n";
                }
            }
            $input = $instruction . $history;
        } else {
            $input = $task;
        }

        Log::info("ResearchAgent: Запуск исследования", ['task' => $input]);

        // ResearchAgent делегирует выполнение LoopController
        $enrichedTask = "Проведи исследование по теме: " . $input . ".
        Если в истории уже есть данные, не дублируй их, а дополни или уточни.
        Используй инструменты поиска данных.";

        return $this->loopController->execute($enrichedTask);
    }
}
