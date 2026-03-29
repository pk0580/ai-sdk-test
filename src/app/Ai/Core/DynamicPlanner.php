<?php

namespace App\Ai\Core;

use App\Ai\Core\Interfaces\DynamicPlannerInterface;
use App\Ai\Core\Plans\OrchestrationStep;
use App\Ai\Core\State\AgentState;
use App\Ai\Agents\PlannerAgent;
use Illuminate\Support\Facades\Log;

class DynamicPlanner implements DynamicPlannerInterface
{
    private string $prompt = "Ты — Динамический Планировщик (Dynamic Planner) в мульти-агентной системе.
    Твоя задача — проанализировать исходный запрос пользователя, историю выполненных шагов и текущий контекст, чтобы решить, какой шаг будет следующим.

    Доступные агенты:
    1. research — эксперт по поиску информации и выполнению расчетов. Используй его, когда нужно собрать факты, цифры или уточнить данные.
    2. summary — мастер синтеза и выводов. Используй его в конце процесса для формирования итогового ответа на основе всех собранных данных research.

    ПРАВИЛА ПРИНЯТИЯ РЕШЕНИЙ:
    - Если в истории уже есть результаты от research, но их недостаточно для полного ответа, запусти research снова с уточняющим заданием.
    - Если все необходимые данные собраны, запусти summary для финального обобщения.
    - Если summary уже выполнил свою работу и представил качественный отчет, верни JSON с finish: true.
    - Если история пуста, начни с research.

    ФОРМАТ ОТВЕТА (ТОЛЬКО JSON):
    Для следующего шага:
    {
      \"next_step\": {\"agent\": \"research\", \"task\": \"конкретное задание для агента\"},
      \"thought\": \"твое краткое рассуждение, почему выбран этот шаг\"
    }

    Для завершения:
    {
      \"finish\": true,
      \"thought\": \"краткое пояснение, почему данных достаточно\"
    }";

    public function initialStep(string $message): OrchestrationStep
    {
        // По умолчанию первым шагом всегда идет research для сбора данных,
        return new OrchestrationStep('research', $message);
    }

    public function nextStep(AgentState $state): ?OrchestrationStep
    {
        $historyText = json_encode($state->history, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        $currentContext = $state->context ?: 'Контекст пуст';

        $message = "ИСХОДНЫЙ ЗАПРОС: {$state->input}\n\n" .
                  "ТЕКУЩИЙ КОНТЕКСТ (последний результат): {$currentContext}\n\n" .
                  "ИСТОРИЯ ВЫПОЛНЕНИЯ:\n{$historyText}";

        try {
            /** @var PlannerAgent $agent */
            $agent = app(PlannerAgent::class, [
                'instructions' => $this->prompt,
                'messages' => [],
                'tools' => []
            ]);
            $response = $agent->prompt($message);
            $text = (string) $response;

            Log::info("DynamicPlanner: Ответ от LLM", ['response' => $text]);

            $jsonStart = strpos($text, '{');
            $jsonEnd = strrpos($text, '}');

            if ($jsonStart === false || $jsonEnd === false) {
                return null;
            }

            $jsonContent = substr($text, $jsonStart, $jsonEnd - $jsonStart + 1);
            $data = json_decode($jsonContent, true);

            // Если SDK замокан через fakeAgent, ответ приходит в поле 'result'
            if (isset($data['result']) && is_string($data['result'])) {
                $innerJson = $data['result'];
                $innerStart = strpos($innerJson, '{');
                $innerEnd = strrpos($innerJson, '}');
                if ($innerStart !== false && $innerEnd !== false) {
                    $innerContent = substr($innerJson, $innerStart, $innerEnd - $innerStart + 1);
                    $data = json_decode($innerContent, true);
                }
            }

            if (isset($data['finish']) && $data['finish'] === true) {
                return null;
            }

            if (isset($data['next_step']['agent'], $data['next_step']['task'])) {
                return new OrchestrationStep(
                    $data['next_step']['agent'],
                    $data['next_step']['task']
                );
            }

            return null;
        } catch (\Exception $e) {
            Log::error("DynamicPlanner: Ошибка планирования", ['error' => $e->getMessage()]);
            return null;
        }
    }
}
