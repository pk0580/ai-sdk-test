<?php

namespace App\Ai\Core;

use App\Ai\DTO\Plan;
use App\Ai\DTO\Step;
use App\Ai\Tools\ToolRegistry;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\AnonymousAgent;

class Planner
{
    private ToolRegistry $toolRegistry;

    public function __construct(ToolRegistry $toolRegistry)
    {
        $this->toolRegistry = $toolRegistry;
    }

    private function getInstructions(): string
    {
        $definitions = json_encode($this->toolRegistry->getToolsDefinitions(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        return <<<PROMPT
Ты — ИИ-планировщик. Твоя задача — разбить запрос пользователя на последовательность выполняемых шагов.
Каждый шаг должен указывать используемый инструмент и его параметры.

Доступные инструменты:
{$definitions}

Ответ должен быть СТРОГО в формате JSON:
{
  "steps": [
    {
      "tool": "название",
      "parameters": { ... },
      "description": "описание"
    }
  ]
}
PROMPT;
    }

    public function generate(string $message): Plan
    {
        Log::info("Планировщик: Генерирую план для сообщения", ['message' => $message]);

        try {
            $agent = new AnonymousAgent($this->getInstructions(), [], []);
            $response = $agent->prompt($message);
            $text = (string) $response;

            Log::debug("Планировщик: Ответ LLM", ['text' => $text]);

            // Извлекаем JSON из текста (на случай если LLM добавила пояснения или ```json)
            $jsonStart = strpos($text, '{');
            $jsonEnd = strrpos($text, '}');

            if ($jsonStart === false || $jsonEnd === false) {
                Log::error("Планировщик: JSON не найден в ответе", ['text' => $text]);
                return $this->fallbackPlan();
            }

            $jsonContent = substr($text, $jsonStart, $jsonEnd - $jsonStart + 1);
            $data = json_decode($jsonContent, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::error("Планировщик: Ошибка парсинга JSON", ['error' => json_last_error_msg(), 'content' => $jsonContent]);
                return $this->fallbackPlan();
            }

            if (!isset($data['steps']) || !is_array($data['steps'])) {
                Log::error("Планировщик: Неверная структура JSON", ['data' => $data]);
                return $this->fallbackPlan();
            }

            return Plan::fromArray($data);

        } catch (\Exception $e) {
            Log::error("Планировщик: Ошибка генерации плана", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->fallbackPlan();
        }
    }

    private function fallbackPlan(): Plan
    {
        return new Plan([
            new Step('vector_search', ['query' => 'general help'], 'Резервный шаг из-за ошибки')
        ]);
    }
}
