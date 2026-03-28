<?php

namespace App\Ai\Core;

use App\Ai\Core\Interfaces\OrchestratorPlannerInterface;
use App\Ai\Core\Plans\OrchestrationPlan;
use App\Ai\Core\Plans\OrchestrationStep;
use Laravel\Ai\AnonymousAgent;

class HybridOrchestratorPlanner implements OrchestratorPlannerInterface
{
    public function plan(string $message): OrchestrationPlan
    {
        $lower = mb_strtolower($message);

        // ✅ Deterministic rules
        if (str_contains($lower, 'summarize') || str_contains($lower, 'резюме') || (str_contains($lower, 'кратк') && !str_contains($lower, 'правила'))) {
            return new OrchestrationPlan([
                new OrchestrationStep('research', $message),
                new OrchestrationStep('summary', 'Сделай краткое резюме полученных данных'),
            ]);
        }

        // 🔥 Fallback to LLM
        return $this->llmPlan($message);
    }

    private function llmPlan(string $message): OrchestrationPlan
    {
        $prompt = "Ты — Планировщик (Planner) в мульти-агентной системе.
        Твоя задача — проанализировать запрос и составить план из шагов (агентов).

        Доступные агенты:
        1. research — ищет информацию, проводит расчеты.
        2. summary — делает краткий вывод, резюмирует информацию.

        Пример ответа в формате JSON:
        {
          \"steps\": [
            {\"agent\": \"research\", \"task\": \"исходная задача\"},
            {\"agent\": \"summary\", \"task\": \"сделай резюме\"}
          ]
        }";

        try {
            $agent = new AnonymousAgent($prompt, [], []);
            $response = $agent->prompt($message);
            $text = (string) $response;

            $jsonStart = strpos($text, '{');
            $jsonEnd = strrpos($text, '}');
            if ($jsonStart === false || $jsonEnd === false) {
                return $this->defaultPlan($message);
            }

            $jsonContent = substr($text, $jsonStart, $jsonEnd - $jsonStart + 1);
            $data = json_decode($jsonContent, true);

            if (!isset($data['steps']) || !is_array($data['steps'])) {
                return $this->defaultPlan($message);
            }

            $steps = [];
            foreach ($data['steps'] as $stepData) {
                if (isset($stepData['agent'], $stepData['task'])) {
                    $steps[] = new OrchestrationStep($stepData['agent'], $stepData['task']);
                }
            }

            return count($steps) > 0 ? new OrchestrationPlan($steps) : $this->defaultPlan($message);
        } catch (\Exception $e) {
            return $this->defaultPlan($message);
        }
    }

    private function defaultPlan(string $message): OrchestrationPlan
    {
        return new OrchestrationPlan([
            new OrchestrationStep('research', $message)
        ]);
    }
}
