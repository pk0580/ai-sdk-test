<?php

namespace App\Ai\Core;

use App\Ai\Core\Interfaces\DynamicPlannerInterface;
use App\Ai\Core\Plans\Step;
use App\Ai\Core\State\AgentState;
use App\Ai\Agents\PlannerAgent;
use App\Ai\Utils\JsonSanitizer;
use Illuminate\Support\Facades\Log;

class DynamicPlanner implements DynamicPlannerInterface
{
    const int HISTORY_LIMIT = 5;

    private string $prompt = "Ты — Динамический Планировщик.
    Твоя задача — решить, какой шаг будет следующим.

    Доступные агенты:
    1. research — поиск информации.
    2. summary — итоговый ответ.

    ФОРМАТ ОТВЕТА (ТОЛЬКО JSON):
    {
      \"next_step\": {\"agent\": \"research\", \"task\": \"задание\"}
    }
    Или если всё готово:
    {
      \"finish\": true
    }";

    public function decideNextStep(AgentState $state): ?Step
    {
        try {
            if (empty($state->history)) {
                return new Step('research', $state->input);
            }

            // Если последний был research и удачно, и данных достаточно (или лимит) - summary.
            $lastEntry = end($state->history);
            if ($lastEntry['agent'] === 'summary' && $lastEntry['success']) {
                return null;
            }

            if ($lastEntry['agent'] === 'research' && $lastEntry['success']) {
                // Если в результате есть метка завершения или мы решили что хватит
                if (str_contains($lastEntry['result'], '[RESEARCH_FINISHED]')
                    || count($state->history) >= self::HISTORY_LIMIT
                ) {
                    return new Step('summary', "Подведи итог: " . $state->input);
                }
            }

            // В остальных случаях спрашиваем LLM (упрощенно)
            $message = "Запрос: {$state->input}\nИстория: " . json_encode($state->history);
            $response = $this->askPlanner($message);
            $data = $this->parseResponse($response);

            if (!$data || !empty($data['finish'])) {
                return null;
            }

            return new Step($data['next_step']['agent'], $data['next_step']['task']);

        } catch (\Exception $e) {
            Log::error("DynamicPlanner Error: " . $e->getMessage());
            return null;
        }
    }

    private function askPlanner(string $message): string
    {
        $agent = app(PlannerAgent::class, [
            'instructions' => $this->prompt,
        ]);

        return (string) $agent->prompt($message);
    }

    private function parseResponse(string $text): ?array
    {
        $jsonStart = strpos($text, '{');
        $jsonEnd = strrpos($text, '}');

        if ($jsonStart === false || $jsonEnd === false) {
            return null;
        }

        $jsonContent = substr($text, $jsonStart, $jsonEnd - $jsonStart + 1);
        return json_decode($jsonContent, true);
    }
}
