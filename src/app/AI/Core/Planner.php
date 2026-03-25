<?php

namespace App\AI\Core;

use App\AI\DTO\Plan;
use App\AI\DTO\Step;
use Illuminate\Support\Facades\Log;
use Laravel\AI\Facades\AI;

class Planner
{
    private const SYSTEM_PROMPT = <<<PROMPT
Вы — ИИ-планировщик. Ваша задача — разбить запрос пользователя на последовательность выполнимых шагов.
Каждый шаг должен указывать используемый инструмент и его параметры.

Доступные инструменты:
1. calculator - используется для математических вычислений. Параметры: { "expression": "string" }
2. vector_search - используется для поиска информации в базе знаний. Параметры: { "query": "string" }

Ответ должен быть валидным JSON-объектом с массивом "steps".
Каждый шаг в массиве "steps" должен содержать:
- "tool": название инструмента (calculator, vector_search)
- "parameters": объект с параметрами, специфичными для инструмента
- "description": краткое описание того, почему этот шаг необходим

Пример вывода:
{
  "steps": [
    {
      "tool": "vector_search",
      "parameters": { "query": "laravel scaling" },
      "description": "Поиск информации о масштабировании Laravel"
    }
  ]
}
PROMPT;

    public function generate(string $message): Plan
    {
        Log::info("Планировщик: Генерирую план для сообщения", ['message' => $message]);

        try {
            $response = AI::chat()
                ->system(self::SYSTEM_PROMPT)
                ->user($message)
                ->json() // Просим вернуть JSON
                ->send();

            $data = $response->json();

            Log::debug("Планировщик: Ответ LLM", ['data' => $data]);

            if (!isset($data['steps']) || !is_array($data['steps'])) {
                Log::error("Планировщик: Неверный формат JSON-ответа", ['response' => $data]);
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
