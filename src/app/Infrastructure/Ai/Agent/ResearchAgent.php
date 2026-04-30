<?php

declare(strict_types=1);

namespace App\Infrastructure\Ai\Agent;

use App\Domain\Ai\Conversation\Conversation;
use App\Domain\Ai\Tooling\ToolRegistryInterface;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Attributes\MaxSteps;
use Laravel\Ai\Attributes\Timeout;

#[MaxSteps(10)]
#[Timeout(600)]
class ResearchAgent extends BaseAgent
{
    private const int MAX_ITERATIONS = 3;

    public function __construct(private readonly ToolRegistryInterface $toolRegistry)
    {
        parent::__construct(
            name: 'ResearchAgent',
            systemPrompt: "Ты — Research Agent. Твоя задача — изучить заданную тему, используя ТОЛЬКО доступные тебе инструменты.
        Твой результат должен содержать только ту информацию, которую ты нашел, используя доступные тебе инструменты.
        ЕСЛИ ТЫ УЖЕ НАШЕЛ ОТВЕТ (например, результат математического вычисления), ТЫ ОБЯЗАН ЗАВЕРШИТЬ ИССЛЕДОВАНИЕ.
        Если в базе нет данных - завершай исследование",
        );
    }

    public function tools(): iterable
    {
        return $this->toolRegistry->all();
    }

    public function execute(string $task, Conversation $conversation): string
    {
        Log::info('ResearchAgent: starting research', ['task' => $task]);

        $results = '';
        $currentTask = $task;

        for ($i = 0; $i < self::MAX_ITERATIONS; $i++) {
            Log::info("ResearchAgent: iteration {$i}", ['task' => $currentTask]);

            $iterationResult = (string) $this->prompt($currentTask);
            Log::info('ResearchAgent: prompt returned', ['result' => $iterationResult]);

            $results .= "\nИтерация {$i}:\n" . $iterationResult;

            $decision = $this->reflect($iterationResult);
            Log::info('ResearchAgent: reflection decision', ['decision' => $decision]);

            if (($decision['finish'] ?? true) === true) {
                return $results . "\n[RESEARCH_FINISHED]";
            }

            if (empty($decision['next_task'] ?? '')) {
                return $results . "\n[RESEARCH_FINISHED]";
            }

            $currentTask = $decision['next_task'];
        }

        Log::info("ResearchAgent results: {$results}");

        return $results;
    }

    /** @return array{finish: bool, next_task?: string} */
    private function reflect(string $results): array
    {
        if ($this->containsFinalAnswer($results)) {
            return ['finish' => true, 'next_task' => ''];
        }

        $reflectionPrompt = "Проанализируй результаты. Если в результатах УЖЕ есть ответ на вопрос пользователя (например, результат вычисления 2+2=4), ВСЕГДА устанавливай 'finish': true.

        Если результат содержит только число или предложение с результатом математических вычислений (как 'Результат вычисления: 10' или 'Ответ: 10'), ВСЕГДА устанавливай 'finish': true.

        Ответь СТРОГО в формате JSON:
        - Если ответ найден: {\"finish\": true}
        - Если данных для ответа действительно нет НИКАКИХ (результат пуст): {\"finish\": false, \"next_task\": \"уточненный запрос для поиска конкретной недостающей информации\"}

        Результаты для анализа: {$results}";

        try {
            $response = $this->ask($reflectionPrompt);
            Log::info('ResearchAgent: ask returned', ['response' => $response]);

            $jsonStart = strpos($response, '{');
            $jsonEnd = strrpos($response, '}');
            if ($jsonStart !== false && $jsonEnd !== false && $jsonEnd > $jsonStart) {
                $data = json_decode(substr($response, $jsonStart, $jsonEnd - $jsonStart + 1), true);
                if (is_array($data)) {
                    return [
                        'finish' => $data['finish'] ?? true,
                        'next_task' => $data['next_task'] ?? '',
                    ];
                }
            }
        } catch (\Throwable $e) {
            Log::error('ResearchAgent reflection error: ' . $e->getMessage());
        }

        return ['finish' => true];
    }

    private function containsFinalAnswer(string $results): bool
    {
        $normalized = trim($results);

        if ($normalized === '') {
            return false;
        }

        if (preg_match('/^\s*-?\d+(?:[.,]\d+)?\s*$/u', $normalized) === 1) {
            return true;
        }

        if (preg_match('/\b(ответ|результат(?: вычисления)?)\s*:\s*-?\d+(?:[.,]\d+)?\b/ui', $normalized) === 1) {
            return true;
        }

        return false;
    }
}
