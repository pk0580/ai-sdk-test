<?php

namespace App\Ai\Agents;

use App\Ai\Tools\ToolRegistry;
use App\Ai\Core\LoopController;
use Laravel\Ai\Attributes\MaxSteps;
use Laravel\Ai\Attributes\Timeout;
use Illuminate\Support\Facades\Log;

#[MaxSteps(10)]
#[Timeout(120)]
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

    public function execute(string $task): string
    {
        Log::info("ResearchAgent: Запуск исследования", ['task' => $task]);

        // ResearchAgent делегирует выполнение LoopController
        // Мы можем добавить дополнительные инструкции к задаче
        $enrichedTask = "Проведи исследование по теме: " . $task . ". Используй инструменты поиска данных.";

        return $this->loopController->execute($enrichedTask);
    }
}
