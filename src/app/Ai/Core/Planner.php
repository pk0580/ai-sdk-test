<?php

namespace App\Ai\Core;

use App\Ai\DTO\Plan;
use App\Ai\DTO\Step;
use App\Ai\Tools\ToolRegistry;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use App\Ai\Agents\CheapAnonymousAgent;
use Laravel\Ai\AnonymousAgent;

class Planner
{
    private ToolRegistry $toolRegistry;

    public function __construct(ToolRegistry $toolRegistry)
    {
        $this->toolRegistry = $toolRegistry;
    }

    private function createAgent(string $instructions): AnonymousAgent
    {
        return new CheapAnonymousAgent($instructions, [], []);
    }

    private function getResponse(AnonymousAgent $agent, string $message): string
    {
        $maxAttempts = 2;
        $attempt = 0;

        while ($attempt < $maxAttempts) {
            try {
                $response = $agent->prompt($message);
                return (string) $response;
            } catch (\Exception $e) {
                $attempt++;
                Log::warning("Планировщик: Попытка {$attempt} не удалась", [
                    'error' => $e->getMessage()
                ]);

                if ($attempt >= $maxAttempts) {
                    throw $e;
                }

                // Небольшая задержка перед повтором
                usleep(500000); // 0.5 сек
            }
        }

        return '';
    }

    private function getInstructions(): string
    {
        $definitions = json_encode($this->toolRegistry->getToolsDefinitions(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        return <<<PROMPT
Ты — ИИ-планировщик. Твоя задача — разбить запрос пользователя на минимально необходимую последовательность выполняемых шагов.
Каждый шаг должен указывать используемый инструмент и его параметры.

Доступные инструменты:
{$definitions}

ОСОБЫЕ ПРАВИЛА:
1. Используй ТОЛЬКО релевантные инструменты. Не используй `calculator`, если в запросе нет математических вычислений.
2. Для поиска информации используй `vector_search`. Формулируй четкие поисковые запросы на языке оригинала или на языке системы (обычно русский/английский).
3. Если запрос требует комплексного ответа, начни с поиска информации.
4. ОТВЕЧАЙ ТОЛЬКО JSON-ОБЪЕКТОМ. НИКАКИХ ПОЯСНЕНИЙ ДО ИЛИ ПОСЛЕ.

Ответ должен быть СТРОГО в формате JSON:
{
  "steps": [
    {
      "tool": "название",
      "parameters": { ... },
      "description": "описание цели шага"
    }
  ]
}
PROMPT;
    }

    public function generate(string $message): Plan
    {
        Log::info("Планировщик: Генерирую план для сообщения", ['message' => $message]);

        // $cacheKey = 'ai_plan_' . md5($message . json_encode($this->toolRegistry->getToolsDefinitions()));

        // if (Cache::has($cacheKey)) {
        //     Log::info("Планировщик: Использую кешированный план");
        //     return Plan::fromArray(Cache::get($cacheKey));
        // }

        try {
            $agent = $this->createAgent($this->getInstructions());
            $text = $this->getResponse($agent, $message);

            $plan = $this->parsePlan($text);

            // Кешируем на 1 час
            // Cache::put($cacheKey, $plan->toArray(), 3600);

            return $plan;

        } catch (\Exception $e) {
            Log::error("Планировщик: Ошибка генерации плана", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->fallbackPlan();
        }
    }

    /**
     * Превращает текстовое предложение (suggestion) в один Step.
     */
    public function parseStep(string $suggestion): ?Step
    {
        Log::info("Планировщик: Парсинг предложения в шаг", ['suggestion' => $suggestion]);

        try {
            $agent = $this->createAgent($this->getInstructions());
            $text = $this->getResponse($agent, "Сгенерируй ОДИН шаг для выполнения следующего предложения: " . $suggestion);

            $plan = $this->parsePlan($text);
            $steps = $plan->steps->all();

            return !empty($steps) ? $steps[0] : null;

        } catch (\Exception $e) {
            Log::error("Планировщик: Ошибка парсинга предложения", [
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    private function parsePlan(string $text): Plan
    {
        Log::debug("Планировщик: Ответ LLM", ['text' => $text]);

        // Извлекаем JSON из текста (на случай если LLM добавила пояснения или ```json)
        $jsonStart = strpos($text, '{');
        $jsonEnd = strrpos($text, '}');

        if ($jsonStart === false || $jsonEnd === false) {
            Log::error("Планировщик: JSON не найден в ответе", ['text' => $text]);
            return $this->fallbackPlan();
        }

        $jsonContent = substr($text, $jsonStart, $jsonEnd - $jsonStart + 1);

        // Очистка от markdown блоков и лишних символов
        $jsonContent = preg_replace('/^```json\s*/i', '', $jsonContent);
        $jsonContent = preg_replace('/```$/', '', $jsonContent);
        $jsonContent = trim($jsonContent);

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
    }

    private function fallbackPlan(): Plan
    {
        return new Plan([
            new Step('vector_search', ['query' => 'general help'], 'Резервный шаг из-за ошибки')
        ]);
    }
}
