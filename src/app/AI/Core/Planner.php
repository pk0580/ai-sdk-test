<?php

namespace App\AI\Core;

use App\AI\DTO\Plan;
use App\AI\DTO\Step;
use Illuminate\Support\Facades\Log;
use function Laravel\Ai\{agent};

class Planner
{
    private const string INSTRUCTIONS = <<<PROMPT
Ты — ИИ-планировщик. Твоя задача — разбить запрос пользователя на последовательность выполняемых шагов.
Каждый шаг должен указывать используемый инструмент и его параметры.

Доступные инструменты:
1. calculator - используется для математических вычислений. Параметры: { "expression": "string" }
2. vector_search - используется для поиска информации в базе знаний. Параметры: { "query": "string" }

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

    public function generate(string $message): Plan
    {
        Log::info("Планировщик: Генерирую план для сообщения", ['message' => $message]);

        try {
            $response = agent(self::INSTRUCTIONS, [], [])->prompt($message);
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
