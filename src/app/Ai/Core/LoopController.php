<?php

namespace App\Ai\Core;

use App\Ai\DTO\Plan;
use App\Ai\DTO\Step;
use App\Ai\Tools\ToolRegistry;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\AnonymousAgent;
use Laravel\Ai\Tools\Request;

class LoopController
{
    private Planner $planner;
    private Reflector $reflector;
    private ToolRegistry $toolRegistry;
    private int $maxIterations = 5;

    public function __construct(
        Planner $planner,
        Reflector $reflector,
        ToolRegistry $toolRegistry,
        int $maxIterations = 5
    ) {
        $this->planner = $planner;
        $this->reflector = $reflector;
        $this->toolRegistry = $toolRegistry;
        $this->maxIterations = $maxIterations;
    }

    /**
     * Запускает цикл выполнения задачи.
     * Возвращает итоговый результат.
     */
    public function execute(string $userMessage): string
    {
        Log::info("LoopController: Начало выполнения", ['message' => $userMessage]);

        // 1. Генерируем начальный план
        $plan = $this->planner->generate($userMessage);
        $executedSteps = [];
        $currentIteration = 0;

        // Очередь шагов к выполнению
        $stepsToExecute = $plan->steps->all();

        while (!empty($stepsToExecute) && $currentIteration < $this->maxIterations) {
            $step = array_shift($stepsToExecute);
            $currentIteration++;

            Log::info("LoopController: Шаг {$currentIteration}", [
                'tool' => $step->tool,
                'parameters' => $step->parameters
            ]);

            // 2. Выполняем инструмент
            $toolResult = $this->executeStep($step);
            $executedSteps[] = [
                'step' => $step,
                'result' => $toolResult
            ];

            // 3. Рефлексия (нужно ли продолжать?)
            $reflection = $this->reflector->reflect($userMessage, $step, $toolResult);

            Log::debug("LoopController: Рефлексия", $reflection);

            if ($reflection['decision'] === 'finish') {
                Log::info("LoopController: Рефлектор решил завершить.");
                return $this->formatFinalResponse($userMessage, $executedSteps, $reflection['thought']);
            }

            // 4. Если нужно продолжить, проверяем, предложил ли рефлектор новый шаг
            if ($reflection['decision'] === 'continue' && isset($reflection['next_suggestion'])) {
                $nextStep = $this->parseNextStep($reflection['next_suggestion']);
                if ($nextStep) {
                    array_unshift($stepsToExecute, $nextStep);
                    Log::info("LoopController: Добавлен новый шаг из рефлексии", ['tool' => $nextStep->tool]);
                }
            }
        }

        if ($currentIteration >= $this->maxIterations) {
            Log::warning("LoopController: Достигнут лимит итераций.");
        }

        return $this->formatFinalResponse($userMessage, $executedSteps, "Выполнено {$currentIteration} шагов.");
    }

    private function executeStep(Step $step): mixed
    {
        $tool = $this->toolRegistry->get($step->tool);
        if (!$tool) {
            Log::error("LoopController: Инструмент не найден", ['tool' => $step->tool]);
            return "Ошибка: Инструмент {$step->tool} не найден.";
        }

        try {
            $request = new Request($step->parameters);
            return $tool->handle($request);
        } catch (\Exception $e) {
            Log::error("LoopController: Ошибка при выполнении инструмента", [
                'tool' => $step->tool,
                'error' => $e->getMessage()
            ]);
            return "Ошибка при выполнении: " . $e->getMessage();
        }
    }

    private function parseNextStep(string $suggestion): ?Step
    {
        return $this->planner->parseStep($suggestion);
    }

    private function formatFinalResponse(string $userMessage, array $executedSteps, string $finalThought): string
    {
        Log::info("LoopController: Формирование финального ответа");

        $history = "";
        foreach ($executedSteps as $i => $item) {
            /** @var Step $step */
            $step = $item['step'];
            $result = is_string($item['result']) ? $item['result'] : json_encode($item['result'], JSON_UNESCAPED_UNICODE);
            $history .= ($i + 1) . ". Инструмент [{$step->tool}]: " . ($step->description ?? '') . "\n";
            $history .= "   Результат: " . mb_substr($result, 0, 500) . (mb_strlen($result) > 500 ? "..." : "") . "\n\n";
        }

        $prompt = <<<PROMPT
Ты — ИИ-ответчик (Responder Agent). Твоя задача — составить финальный, человекопонятный ответ пользователю на основе истории выполненных действий и заключительных мыслей.

Запрос пользователя: {$userMessage}
Финальное заключение: {$finalThought}

История выполнения:
{$history}

Сформулируй краткий, точный и полезный ответ на языке пользователя. Не упоминай названия инструментов, если это не требуется для понимания.
PROMPT;

        try {
            $agent = new AnonymousAgent($prompt, [], []);
            $response = $agent->prompt("Сформулируй финальный ответ.");
            return (string) $response;
        } catch (\Exception $e) {
            Log::error("LoopController: Ошибка при формировании ответа", ['error' => $e->getMessage()]);
            return "Финальный анализ: {$finalThought}\n\nИстория:\n{$history}";
        }
    }
}
