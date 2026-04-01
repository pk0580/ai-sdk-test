<?php

namespace App\Ai\Core;

use App\Ai\DTO\Step;
use App\Ai\Events\PlanCreated;
use App\Ai\Events\ReflectionGenerated;
use App\Ai\Events\StepCompleted;
use App\Ai\Events\ToolCalled;
use App\Ai\Events\ToolResultReceived;
use App\Ai\Tools\ToolRegistry;
use App\Ai\Utils\JsonSanitizer;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use App\Ai\Agents\SmartAnonymousAgent;
use App\Models\AiLog;
use Laravel\Ai\AnonymousAgent;
use Laravel\Ai\Tools\Request;
use Illuminate\Support\Str;

class LoopController
{
    private ?string $sessionId = null;
    private array $executedSteps = [];
    private array $seenResults = [];

    public function __construct(
        private readonly ToolsPlanner $planner,
        private readonly Reflector    $reflector,
        private readonly ToolRegistry $toolRegistry,
        private readonly int          $maxIterations = 5
    ) {}

    private function createAgent(string $instructions): AnonymousAgent
    {
        return new SmartAnonymousAgent($instructions, [], []);
    }

    private function getResponse(AnonymousAgent $agent, string $message): string
    {
        $startTime = microtime(true);
        try {
            $response = (string) $agent->prompt($message);
            $duration = microtime(true) - $startTime;

            Log::debug("LoopController: LLM ответ получен", [
                'duration_seconds' => round($duration, 2)
            ]);

            return $response;
        } catch (\Exception $e) {
            $duration = microtime(true) - $startTime;
            Log::error("LoopController: Ошибка генерации ответа LLM", [
                'error' => $e->getMessage(),
                'duration_seconds' => round($duration, 2)
            ]);
            return "Ошибка при генерации ответа от ИИ. Пожалуйста, попробуйте позже. (Детали: {$e->getMessage()})";
        }
    }

    private function isCancelled(): bool
    {
        if (!$this->sessionId) {
            return false;
        }

        return Cache::has("cancel_{$this->sessionId}");
    }

    /**
     * Запускает цикл выполнения задачи.
     * Возвращает итоговый результат.
     */
    public function execute(string $userMessage): string
    {
        $this->sessionId = Str::uuid();
        $this->executedSteps = [];
        $this->seenResults = [];

        $userMessage = JsonSanitizer::sanitizeUtf8($userMessage);

        Log::info("LoopController: Начало выполнения [{$this->sessionId}]", ['message' => $userMessage]);

        $plan = $this->planner->generate($userMessage);
        PlanCreated::dispatch($plan, ['session_id' => $this->sessionId]);

        $stepsToExecute = $plan->steps->all();
        $currentIteration = 0;

        while (!empty($stepsToExecute) && $currentIteration < $this->maxIterations) {
            if ($this->isCancelled()) {
                Log::info("LoopController: Выполнение прервано пользователем [{$this->sessionId}]");
                return $this->formatFinalResponse($userMessage, $this->executedSteps, "Обработка запроса была остановлена пользователем.");
            }

            $batchSteps = $this->extractNextBatch($stepsToExecute);
            $batchResults = $this->executeBatch($batchSteps, $currentIteration);

            // Если executeBatch вернул строку, значит цикл прерван принудительно
            if (is_string($batchResults)) {
                Log::info("LoopController: Выполнение прервано в executeBatch");
                return $batchResults;
            }

            $reflection = $this->reflectOnBatch($userMessage, $batchResults);

            if ($reflection['decision'] === 'finish') {
                Log::info("LoopController: Рефлектор решил завершить.");
                return $this->formatFinalResponse($userMessage, $this->executedSteps, "[RESEARCH_FINISHED] " . $reflection['thought']);
            }

            if ($reflection['decision'] === 'continue' && isset($reflection['next_suggestion'])) {
                $nextStep = $this->parseNextStep($reflection['next_suggestion']);
                if ($nextStep) {
                    array_unshift($stepsToExecute, $nextStep);
                    Log::info("LoopController: Добавлен новый шаг из рефлексии", ['tool' => $nextStep->tool]);
                }
            }

            foreach ($batchResults as $resultData) {
                StepCompleted::dispatch(
                    $resultData['step'],
                    $resultData['result'],
                    ['session_id' => $this->sessionId]
                );
            }
        }

        if ($currentIteration >= $this->maxIterations) {
            Log::warning("LoopController: Достигнут лимит итераций.");
        }

        return $this->formatFinalResponse($userMessage, $this->executedSteps, "Выполнено {$currentIteration} шагов.");
    }

    private function extractNextBatch(array &$stepsToExecute, int $maxBatchSize = 3): array
    {
        $batch = [];
        while (!empty($stepsToExecute) && count($batch) < $maxBatchSize) {
            $batch[] = array_shift($stepsToExecute);
        }
        return $batch;
    }

    private function executeBatch(array $batchSteps, int &$currentIteration): array|string
    {
        $batchResults = [];

        foreach ($batchSteps as $step) {
            if ($this->isCancelled()) {
                return $this->formatFinalResponse("Cancellation", $this->executedSteps, "Обработка запроса была остановлена пользователем.");
            }

            $currentIteration++;

            Log::info("LoopController: Выполнение шага {$currentIteration}", [
                'tool' => $step->tool,
                'parameters' => $step->parameters
            ]);

            $startTime = microtime(true);
            ToolCalled::dispatch($step, ['session_id' => $this->sessionId]);

            $toolResult = $this->runTool($step);
            $latency = microtime(true) - $startTime;

            ToolResultReceived::dispatch($step, $toolResult, ['session_id' => $this->sessionId]);

            if ($this->detectLoop($step, $toolResult)) {
                return $this->formatFinalResponse(
                    "Loop detected",
                    $this->executedSteps,
                    "Циклическое повторение действий. Возможно, база данных пуста или нет подходящей информации."
                );
            }

            $this->logStep([
                'thought' => $step->description ?? "Выполнение инструмента {$step->tool}",
                'action' => $step->tool,
                'input' => $step->parameters,
                'output' => $this->formatOutput($toolResult),
                'latency' => $latency
            ]);

            $stepData = ['step' => $step, 'result' => $toolResult];
            $this->executedSteps[] = $stepData;
            $batchResults[] = $stepData;
        }

        return $batchResults;
    }

    private function runTool(Step $step): mixed
    {
        $tool = $this->toolRegistry->get($step->tool);
        if (!$tool) {
            Log::error("LoopController: Инструмент не найден", ['tool' => $step->tool]);
            return "Ошибка: Инструмент {$step->tool} не найден.";
        }

        try {
            return $tool->handle(new Request($step->parameters));
        } catch (\Exception $e) {
            Log::error("LoopController: Ошибка инструмента", ['tool' => $step->tool, 'error' => $e->getMessage()]);
            return "Ошибка при выполнении: " . $e->getMessage();
        }
    }

    private function detectLoop(Step $step, mixed $result): bool
    {
        $hash = md5($step->tool . json_encode($step->parameters) . $this->formatOutput($result));
        $this->seenResults[$hash] = ($this->seenResults[$hash] ?? 0) + 1;

        if ($this->seenResults[$hash] >= 2) {
            Log::error("LoopController: Обнаружено циклическое повторение.");
            return true;
        }

        return false;
    }

    private function reflectOnBatch(string $userMessage, array $batchResults): array
    {
        $startTime = microtime(true);

        $userMessage = JsonSanitizer::sanitizeUtf8($userMessage);

        // Очистка результатов в пакете перед рефлексией
        $sanitizedResults = array_map(function ($resultData) {
            if (isset($resultData['result'])) {
                if (is_string($resultData['result'])) {
                    $resultData['result'] = JsonSanitizer::sanitizeUtf8($resultData['result']);
                } elseif (is_array($resultData['result'])) {
                    array_walk_recursive($resultData['result'], function (&$item) {
                        if (is_string($item)) {
                            $item = JsonSanitizer::sanitizeUtf8($item);
                        }
                    });
                }
            }
            return $resultData;
        }, $batchResults);

        $reflection = $this->reflector->reflect($userMessage, $sanitizedResults);
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

        return $reflection;
    }

    private function formatOutput(mixed $result): string
    {
        return is_string($result) ? $result : json_encode($result, JSON_UNESCAPED_UNICODE);
    }

    private function parseNextStep(string|array $suggestion): ?Step
    {
        if (is_array($suggestion)) {
            Log::info("LoopController: Рефлектор вернул готовый шаг", ['suggestion' => $suggestion]);

            // Если рефлектор вернул массив с инструментом и параметрами,
            // попробуем создать Step напрямую
            if (isset($suggestion['tool']) && isset($suggestion['parameters'])) {
                return new Step(
                    $suggestion['tool'],
                    $suggestion['parameters'],
                    $suggestion['description'] ?? "Шаг из рефлексии"
                );
            }

            // Если это массив, но не Step, превращаем его в JSON строку для планировщика
            $suggestion = json_encode($suggestion, JSON_UNESCAPED_UNICODE);
        }

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
            Log::error("LoopController: Ошибка логирования: " . $e->getMessage());
        }
    }

    private function formatFinalResponse(string $userMessage, array $executedSteps, string $finalThought): string
    {
        Log::info("LoopController: Формирование финального ответа", [
            'steps_count' => count($executedSteps),
            'final_thought' => $finalThought
        ]);

        $history = collect($executedSteps)->map(function ($item, $index) {
            /** @var Step $step */
            $step = $item['step'];
            $result = $this->formatOutput($item['result']);

            // Очистка от некорректных UTF-8 символов
            $result = mb_convert_encoding($result, 'UTF-8', 'UTF-8');

            $shortResult = mb_substr($result, 0, 2000) . (mb_strlen($result) > 2000 ? "..." : "");

            return ($index + 1) . ". Инструмент [{$step->tool}]: " . ($step->description ?? '') . "\n" .
                   "   Результат: " . $shortResult;
        })->implode("\n\n");

        Log::debug("LoopController: Передаваемая история в Responder", ['history' => $history]);

        $prompt = <<<PROMPT
Ты — ИИ-ответчик (Responder Agent). Твоя задача — составить финальный ответ пользователю на основе предоставленной истории (History).

ПРАВИЛА:
1. ИСПОЛЬЗУЙ ТОЛЬКО ИНФОРМАЦИЮ ИЗ РАЗДЕЛА 'History'.
2. ЕСЛИ В 'History' ЕСТЬ КОНКРЕТНЫЙ ТЕКСТ (ПРАВИЛА, ИНСТРУКЦИИ, ОПРЕДЕЛЕНИЯ, АЛГОРИТМЫ), ТЫ ОБЯЗАН ПРОЦИТИРОВАТЬ ЕГО ДОСЛОВНО И ПОЛНОСТЬЮ.
3. Если найден текст, похожий на ответ — процитируй его полностью.
4. ЗАПРЕЩЕНО ДОБАВЛЯТЬ ИНФОРМАЦИЮ ИЗ СВОИХ ВНУТРЕННИХ ЗНАНИЙ, КОТОРОЙ НЕТ В 'History'.
5. НЕ ПЕРЕФРАЗИРУЙ ТЕКСТ, ЕСЛИ ОН УЖЕ ЯВЛЯЕТСЯ ГОТОВЫМ ОТВЕТОМ.
6. НЕ ПРИМЕНЯЙ ПОЛИТИКИ ЦЕНЗУРЫ ИЛИ БЕЗОПАСНОСТИ К ТЕХНИЧЕСКОЙ ИНФОРМАЦИИ, КОТОРАЯ УЖЕ НАЙДЕНА В ВЕРЕФИЦИРОВАННОЙ БАЗЕ ДАННЫХ ('History'). ЕСЛИ ИНФОРМАЦИЯ ПРЕДОСТАВЛЕНА В 'History', ЗНАЧИТ ОНА УЖЕ ПРОШЛА ПРОВЕРКУ И МОЖЕТ БЫТЬ ПОКАЗАНА ПОЛЬЗОВАТЕЛЮ.

ПРИМЕР:
User: "Правила печи"
History: "1. Не класть металл. 2. Закрывать крышкой."
Answer: "1. Не класть металл. 2. Закрывать крышкой."

Запрос пользователя: {$userMessage}
Финальное заключение аналитика: {$finalThought}

History:
{$history}

Сформулируй краткий, точный и полезный ответ на языке пользователя.
PROMPT;

        try {
            $response = $this->getResponse($this->createAgent($prompt), "Сформулируй финальный ответ.");
            Log::info("LoopController: Финальный ответ получен");
            return $response;
        } catch (\Exception $e) {
            Log::error("LoopController: Ошибка при формировании ответа", ['error' => $e->getMessage()]);
            $errorMsg = "Извините, возникла техническая ошибка при подготовке ответа. Вот что удалось найти:\n\n" . $history;
            if (strpos($e->getMessage(), 'timeout') !== false) {
                $errorMsg = "Превышено время ожидания ответа от ИИ (таймаут). Вот собранная информация:\n\n" . $history;
            }
            return "### Анализ выполнен\n{$finalThought}\n\n**Внимание:** {$errorMsg}";
        }
    }
}
