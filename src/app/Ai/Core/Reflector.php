<?php

namespace App\Ai\Core;

use App\Ai\DTO\Step;
use App\Ai\Tools\ToolRegistry;
use App\Ai\Utils\JsonSanitizer;
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
Ты — Reflector.

Твоя задача — решить:
достаточно ли данных для ответа или нужно продолжать..

Доступные инструменты:
{$definitions}

ПРАВИЛА:

1. Если найдено объяснение или основные данные — ЗАВЕРШИ (finish). Не запрашивай продолжение, если информация уже в истории.
2. НЕ ищи идеальный ответ до бесконечности.
3. Если есть:
   - алгоритм
   - формула
   - описание процесса
   → этого ДОСТАТОЧНО (decision: finish).

4. Если данные повторяются — ЗАВЕРШИ
5. Если нет новых данных — ЗАВЕРШИ

ФОРМАТ:

{
  "decision": "finish" | "continue",
  "thought": "...",
  "next_suggestion": "..." (если continue)
}
PROMPT;
    }

    public function reflect(string $userMessage, array $batchResults): array
    {
        Log::info("Reflector: Анализирую результаты батча", ['steps_count' => count($batchResults)]);

        // Проверяем на пустую базу ДО вызова LLM, чтобы сэкономить токены и гарантировать результат
        foreach ($batchResults as $item) {
            if (isset($item['result']) && is_string($item['result'])) {
                $res = $item['result'];
                if (str_contains($res, "Knowledge base is empty") || str_contains($res, "No relevant information found")) {
                    Log::info("Reflector: Обнаружен пустой результат поиска. Принудительное завершение.", ['result' => $res]);
                    return [
                        'decision' => 'finish',
                        'thought' => "Поиск по базе знаний не дал результатов: " . $res . ". Дальнейший поиск нецелесообразен.",
                        'next_suggestion' => null
                    ];
                }
            }
        }

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

            // Исправление неэкранированных управляющих символов (переносы строк, табы) внутри JSON строк
            $jsonContent = JsonSanitizer::escapeControlCharacters($jsonContent);

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
