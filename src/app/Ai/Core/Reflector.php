<?php

namespace App\Ai\Core;

use App\Ai\DTO\Step;
use App\Ai\Tools\ToolRegistry;
use Illuminate\Support\Facades\Log;
use App\Ai\Agents\CheapAnonymousAgent;
use Laravel\Ai\AnonymousAgent;
use Laravel\Ai\Ai;

class Reflector
{
    public function __construct(
        private readonly ToolRegistry $toolRegistry
    ) {}

    private function createAgent(string $instructions): AnonymousAgent
    {
        return new CheapAnonymousAgent($instructions, [], []);
    }

    private function getResponse(AnonymousAgent $agent, string $message): string
    {
        $response = $agent->prompt($message);

        return (string) $response;
    }

    private function getInstructions(): string
    {
        $definitions = json_encode($this->toolRegistry->getToolsDefinitions(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        return <<<PROMPT
Ты — ИИ-аналитик (Reflector). Твоя задача — проанализировать результат работы батча инструментов и решить, достаточно ли информации для ответа пользователю или нужно продолжать.

Доступные инструменты:
{$definitions}

Тебе будут предоставлены:
1. Исходный запрос пользователя.
2. Весь батч выполненных шагов (batch_results), где каждый шаг содержит инструмент, параметры и результат.

Твоя задача:
1. Оценить прогресс по всему батчу.
2. Решить, достаточно ли данных для итогового ответа на запрос пользователя.
3. Если данных достаточно — выбирай "finish".
4. Если нужно продолжать, предложи следующий логический шаг. Используй только доступные инструменты.

Ответ должен быть СТРОГО в формате JSON:
{
  "decision": "continue" | "finish",
  "thought": "Твои размышления о результатах батча и следующем шаге",
  "next_suggestion": "Если нужно продолжать, что именно делать дальше?"
}

ОСОБЫЕ ПРАВИЛА:
1. Если результат инструмента "Knowledge base is empty", то продолжать поиск через "vector_search" БЕСПОЛЕЗНО. В этом случае выбирай "finish" и объясни в мыслях, что данных нет.
2. Не предлагай одни и те же действия повторно, если результат не изменился.
3. Если `vector_search` вернул нерелевантную информацию, попробуй `continue` с другим (более точным или более широким) поисковым запросом.
4. Внимательно проверяй, соответствует ли совокупный результат всех шагов запросу.
5. Если в результате какого-либо шага найден текст, который ПОЛНОСТЬЮ или ЗНАЧИТЕЛЬНО отвечает на вопрос, выбирай "finish".
6. Если найденная информация кажется тебе "чувствительной", но она есть в корпоративной базе — она безопасна для показа.
7. Проанализируй данные КАЖДОГО шага батча, чтобы сделать итоговый вывод.
8. Твои предложения (next_suggestion) должны быть СТРОГО релевантны исходному запросу пользователя. Не предлагай абстрактных исследований, не связанных с темой запроса.
9. Если ты видишь, что инструменты возвращают ошибки или не дают новых данных в течение нескольких шагов, выбирай "finish".
10. Используй ТОЛЬКО доступные в системе инструменты. Не выдумывай новые инструменты, такие как "knowledge_base" или другие.
PROMPT;
    }

    public function reflect(string $userMessage, array $batchResults): array
    {
        Log::info("Reflector: Анализирую результаты батча", ['steps_count' => count($batchResults)]);

        $batchForPrompt = array_map(function($item) {
            return [
                'tool' => $item['step']->tool,
                'parameters' => $item['step']->parameters,
                'result' => $item['result']
            ];
        }, $batchResults);

        $input = json_encode([
            'user_query' => $userMessage,
            'batch_results' => $batchForPrompt
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        try {
            $startTime = microtime(true);
            $agent = $this->createAgent($this->getInstructions());
            $text = $this->getResponse($agent, $input);
            $duration = microtime(true) - $startTime;

            Log::debug("Reflector: Ответ LLM", [
                'duration_seconds' => round($duration, 2),
                'text' => $text
            ]);

            // Извлекаем JSON из текста (на случай если LLM добавила пояснения или ```json)
            $jsonStart = strpos($text, '{');
            $jsonEnd = strrpos($text, '}');

            if ($jsonStart === false || $jsonEnd === false) {
                Log::error("Reflector: JSON не найден в ответе", ['text' => $text]);
                return $this->fallbackDecision("Не удалось распарсить ответ LLM. Завершаем работу.");
            }

            $jsonContent = substr($text, $jsonStart, $jsonEnd - $jsonStart + 1);

            // Очистка от markdown блоков и лишних символов
            $jsonContent = preg_replace('/^```json\s*/i', '', $jsonContent);
            $jsonContent = preg_replace('/```$/', '', $jsonContent);
            $jsonContent = trim($jsonContent);

            $data = json_decode($jsonContent, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::error("Reflector: Ошибка парсинга JSON", ['error' => json_last_error_msg(), 'content' => $jsonContent]);
                return $this->fallbackDecision("Ошибка структуры JSON.");
            }

            return [
                'decision' => $data['decision'] ?? 'finish',
                'thought' => $data['thought'] ?? 'Результат получен.',
                'next_suggestion' => $data['next_suggestion'] ?? null
            ];

        } catch (\Exception $e) {
            $duration = isset($startTime) ? microtime(true) - $startTime : 0;
            Log::error("Reflector: Ошибка при вызове LLM", [
                'error' => $e->getMessage(),
                'duration_seconds' => round($duration, 2)
            ]);
            return $this->fallbackDecision("Произошла ошибка при анализе: " . $e->getMessage());
        }
    }

    private function fallbackDecision(string $reason): array
    {
        return [
            'decision' => 'finish',
            'thought' => $reason,
            'next_suggestion' => null
        ];
    }
}
