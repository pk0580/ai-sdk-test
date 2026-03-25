<?php

namespace App\Ai\Core;

use App\Ai\DTO\Step;
use App\Ai\Events\PlanCreated;
use App\Ai\Events\ReflectionGenerated;
use App\Ai\Events\StepCompleted;
use App\Ai\Events\ToolCalled;
use App\Ai\Events\ToolResultReceived;
use App\Ai\Tools\ToolRegistry;
use Illuminate\Support\Facades\Log;
use App\Ai\Agents\SmartAnonymousAgent;
use App\Models\AiLog;
use Laravel\Ai\AnonymousAgent;
use Laravel\Ai\Tools\Request;
use Illuminate\Support\Str;

class LoopController
{
    private Planner $planner;
    private Reflector $reflector;
    private ToolRegistry $toolRegistry;
    private int $maxIterations = 5;
    private ?string $sessionId = null;

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

    private function createAgent(string $instructions): AnonymousAgent
    {
        return new SmartAnonymousAgent($instructions, [], []);
    }

    private function getResponse(AnonymousAgent $agent, string $message): string
    {
        $response = $agent->prompt($message);

        return (string) $response;
    }

    /**
     * Запускает цикл выполнения задачи.
     * Возвращает итоговый результат.
     */
    public function execute(string $userMessage): string
    {
        $this->sessionId = Str::uuid();
        Log::info("LoopController: Начало выполнения [{$this->sessionId}]", ['message' => $userMessage]);

        // 1. Генерируем начальный план
        $plan = $this->planner->generate($userMessage);
        PlanCreated::dispatch($plan, ['session_id' => $this->sessionId]);

        $executedSteps = [];
        $currentIteration = 0;

        // Очередь шагов к выполнению
        $stepsToExecute = $plan->steps->all();

        while (!empty($stepsToExecute) && $currentIteration < $this->maxIterations) {
            // Пытаемся взять пачку независимых шагов (для MVP - просто берем все текущие,
            // но в реальности нужно проверять зависимости).
            // Здесь мы реализуем простой batch: если в очереди больше одного шага,
            // мы можем выполнить их параллельно (в PHP это эмулируется или делается через Promise/Guzzle).
            // Для упрощения и соответствия "batch planning", будем выполнять текущую очередь шагов,
            // пока не потребуется рефлексия.

            $batchResults = [];
            $batchSteps = [];

            // Для простоты: выполняем все текущие шаги плана как один батч,
            // затем делаем одну рефлексию по итогу всей пачки.
            while (!empty($stepsToExecute) && count($batchSteps) < 3) {
                $batchSteps[] = array_shift($stepsToExecute);
            }

            foreach ($batchSteps as $step) {
                $currentIteration++;
                Log::info("LoopController: Выполнение шага {$currentIteration} в батче", [
                    'tool' => $step->tool,
                    'parameters' => $step->parameters
                ]);

                $startTime = microtime(true);
                ToolCalled::dispatch($step, ['session_id' => $this->sessionId]);

                $toolResult = $this->executeStep($step);
                $latency = microtime(true) - $startTime;

                ToolResultReceived::dispatch($step, $toolResult, ['session_id' => $this->sessionId]);

                $this->logStep([
                    'thought' => $step->description ?? "Выполнение инструмента {$step->tool}",
                    'action' => $step->tool,
                    'input' => $step->parameters,
                    'output' => is_string($toolResult) ? $toolResult : json_encode($toolResult, JSON_UNESCAPED_UNICODE),
                    'latency' => $latency
                ]);

                $executedSteps[] = [
                    'step' => $step,
                    'result' => $toolResult
                ];
                $batchResults[] = [
                    'step' => $step,
                    'result' => $toolResult
                ];
            }

            // 3. Рефлексия по последнему шагу батча (или агрегированная)
            // В MVP версии рефлектор принимает один шаг. Передадим последний.
            $lastBatchItem = end($batchResults);
            $startTime = microtime(true);
            $reflection = $this->reflector->reflect($userMessage, $lastBatchItem['step'], $lastBatchItem['result']);
            $latency = microtime(true) - $startTime;

            ReflectionGenerated::dispatch(
                $reflection['decision'],
                $reflection['thought'],
                ['session_id' => $this->sessionId]
            );

            $this->logStep([
                'agent_name' => 'Reflector',
                'thought' => $reflection['thought'],
                'action' => $reflection['decision'],
                'output' => json_encode($reflection, JSON_UNESCAPED_UNICODE),
                'latency' => $latency
            ]);

            Log::debug("LoopController: Рефлексия после батча", $reflection);

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
            StepCompleted::dispatch(['batch_count' => count($batchSteps)], ['session_id' => $this->sessionId]);
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

    private function logStep(array $data): void
    {
        try {
            AiLog::create(array_merge([
                'session_id' => $this->sessionId,
                'agent_name' => 'LoopController',
            ], $data));
        } catch (\Exception $e) {
            Log::error("Ошибка при сохранении лога в БД: " . $e->getMessage());
        }
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
            $agent = $this->createAgent($prompt);
            $text = $this->getResponse($agent, "Сформулируй финальный ответ.");
            return $text;
        } catch (\Exception $e) {
            Log::error("LoopController: Ошибка при формировании ответа", ['error' => $e->getMessage()]);
            return "Финальный анализ: {$finalThought}\n\nИстория:\n{$history}";
        }
    }
}
