<?php

declare(strict_types=1);

namespace App\Infrastructure\Ai\Planner;

use App\Domain\Ai\Tooling\Plan;
use App\Domain\Ai\Tooling\ToolPlannerInterface;
use App\Domain\Ai\Tooling\ToolRegistryInterface;
use App\Domain\Ai\Tooling\ToolStep;
use App\Infrastructure\Ai\Agent\CheapAnonymousAgent;
use App\Infrastructure\Ai\Util\JsonSanitizer;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\AnonymousAgent;

final readonly class LlmToolsPlanner implements ToolPlannerInterface
{
    public function __construct(
        private ToolRegistryInterface $toolRegistry,
        private CacheRepository       $cache,
    ) {}

    public function generate(string $message): Plan
    {
        Log::info('ToolsPlanner: generating plan', ['message' => mb_convert_encoding($message, 'UTF-8', 'UTF-8')]);

        $cacheKey = 'ai_plan_' . md5($message . json_encode($this->toolRegistry->getToolsDefinitions()));

        if ($this->cache->has($cacheKey)) {
            Log::info('ToolsPlanner: using cached plan');
            return Plan::fromArray($this->cache->get($cacheKey));
        }

        try {
            $agent = $this->createAgent($this->getInstructions());
            $text = $this->getResponse($agent, $message);
            $plan = $this->parsePlan($text);

            $this->cache->put($cacheKey, $plan->toArray(), 3600);

            return $plan;
        } catch (\Throwable $e) {
            Log::error('ToolsPlanner: failed to generate plan', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->fallbackPlan($message);
        }
    }

    public function parseStep(string $suggestion): ?ToolStep
    {
        Log::info('ToolsPlanner: parsing suggestion', ['suggestion' => $suggestion]);

        try {
            $agent = $this->createAgent($this->getInstructions());
            $text = $this->getResponse(
                $agent,
                'Сгенерируй ОДИН шаг для выполнения следующего предложения: ' . $suggestion,
            );

            return $this->parsePlan($text)->first();
        } catch (\Throwable $e) {
            Log::error('ToolsPlanner: failed to parse suggestion', ['error' => $e->getMessage()]);

            return new ToolStep('vector_search', ['query' => mb_substr($suggestion, 0, 100)], 'Резервный шаг из-за ошибки');
        }
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
                return (string) $agent->prompt($message);
            } catch (\Throwable $e) {
                $attempt++;
                Log::warning("ToolsPlanner: attempt {$attempt} failed", ['error' => $e->getMessage()]);

                if ($attempt >= $maxAttempts) {
                    throw $e;
                }

                usleep(500000);
            }
        }

        return '';
    }

    private function getInstructions(): string
    {
        $toolsDefinitions = $this->toolRegistry->getToolsDefinitions();

        array_walk_recursive($toolsDefinitions, static function (&$item) {
            if (is_string($item)) {
                $item = mb_convert_encoding($item, 'UTF-8', 'UTF-8');
            }
        });

        $definitions = json_encode($toolsDefinitions, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        return <<<PROMPT
Ты — ИИ-планировщик. Твоя задача — разбить запрос пользователя на минимально необходимую последовательность выполняемых шагов.
Каждый шаг должен указывать используемый инструмент и его параметры.

Доступные инструменты:
{$definitions}

ОСОБЫЕ ПРАВИЛА:
1. Используй ТОЛЬКО релевантные инструменты. Не используй `calculator`, если в запросе нет математических вычислений.
2. Для поиска информации используй `vector_search`. Формулируй четкие поисковые запросы на языке оригинала или на языке системы (обычно русский/английский).
   Если запрос пользователя выглядит как конкретный вопрос или цитата из документа, используй этот текст как поисковый запрос без существенных изменений.
3. Если запрос требует комплексного ответа, начни с поиска информации.
4. ОТВЕЧАЙ ТОЛЬКО JSON-ОБЪЕКТОМ. НИКАКИХ ПОЯСНЕНИЙ ДО ИЛИ ПОСЛЕ.
5. ЗАПРЕЩЕНО выдумывать информацию, которой нет в базе знаний. Если `vector_search` вернул пустой результат или сообщение о пустой базе, НЕ пытайся продолжать поиск.
6. Если база знаний пуста, дальнейший поиск по базе не имеет смысла. Сразу завершай планирование минимальным набором шагов.

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

    private function parsePlan(string $text): Plan
    {
        Log::debug('ToolsPlanner: LLM response', ['text' => $text]);

        $start = strpos($text, '{');
        $end = strrpos($text, '}');

        if ($start === false || $end === false) {
            Log::error('ToolsPlanner: no JSON in response', ['text' => $text]);
            return $this->fallbackPlan('');
        }

        $jsonContent = substr($text, $start, $end - $start + 1);
        $jsonContent = preg_replace('/^```json\s*/i', '', $jsonContent) ?? $jsonContent;
        $jsonContent = preg_replace('/```$/', '', $jsonContent) ?? $jsonContent;
        $jsonContent = trim($jsonContent);
        $jsonContent = JsonSanitizer::escapeControlCharacters($jsonContent);

        $data = json_decode($jsonContent, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::error('ToolsPlanner: JSON parse error', [
                'error' => json_last_error_msg(),
                'content' => $jsonContent,
            ]);
            return $this->fallbackPlan('');
        }

        if (!isset($data['steps']) || !is_array($data['steps'])) {
            Log::error('ToolsPlanner: invalid JSON structure', ['data' => $data]);
            return $this->fallbackPlan('');
        }

        return Plan::fromArray($data);
    }

    private function fallbackPlan(string $message): Plan
    {
        $query = mb_substr($message, 0, 100) ?: 'поиск информации';
        return new Plan([
            new ToolStep('vector_search', ['query' => $query], 'Резервный шаг из-за ошибки планировщика'),
        ]);
    }
}
