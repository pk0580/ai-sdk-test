<?php

namespace App\Ai\Core;

use App\Ai\Core\Interfaces\DynamicPlannerInterface;
use App\Ai\Core\Plans\OrchestrationStep;
use App\Ai\Core\State\AgentState;
use App\Ai\Agents\PlannerAgent;
use App\Ai\Utils\JsonSanitizer;
use Illuminate\Support\Facades\Log;

class DynamicPlanner implements DynamicPlannerInterface
{
    private string $prompt = "Ты — Динамический Планировщик (Dynamic ToolsPlanner) в мульти-агентной системе.
    Твоя задача — проанализировать исходный запрос пользователя, историю выполненных шагов и текущий контекст, чтобы решить, какой шаг будет следующим.

    Доступные агенты:
    1. research — эксперт по поиску информации и выполнению расчетов. Используй его, когда нужно собрать факты, цифры или уточнить данные.
    2. summary — мастер синтеза и выводов. Используй его в конце процесса для формирования итогового ответа на основе всех собранных данных research.

    ПРАВИЛА ПРИНЯТИЯ РЕШЕНИЙ:
    - Если в истории уже есть результаты от research, и в них содержится метка [RESEARCH_FINISHED], это означает, что агент по поиску информации считает свою работу завершенной и данных достаточно. В этом случае ЗАПРЕЩЕНО запускать research повторно. Сразу переходи к summary или finish.
    - Если в истории уже есть результаты от research, но их явно недостаточно для полного ответа (нет метки [RESEARCH_FINISHED]), запусти research снова с уточняющим заданием.
    - Если в истории зафиксирована ОШИБКА (error), проанализируй её. Если ошибка временная (например, лимит или сбой инструмента), попробуй повторить шаг. Если ошибка логическая (инструмент не понимает запрос), предложи ДРУГОЙ подход или перефразируй задание.
    - Если все необходимые данные собраны, запусти summary для финального обобщения.
    - Если summary уже выполнил свою работу и представил качественный отчет, верни JSON с finish: true.
    - Если история пуста, начни с research.
    - ЕСЛИ В ИСТОРИИ БОЛЕЕ 2 РАЗ ПОВТОРЯЕТСЯ ОДНА И ТА ЖЕ ОШИБКА, ПРЕКРАТИ RESEARCH И ВЫЗОВИ SUMMARY С ТЕМ, ЧТО ЕСТЬ.

    ОГРАНИЧЕНИЯ И ГАЛЛЮЦИНАЦИИ:
    - ТЫ ДОЛЖЕН ОТВЕЧАТЬ ТОЛЬКО НА ОСНОВЕ ПРЕДОСТАВЛЕННЫХ ДАННЫХ ИЗ ИСТОРИИ И КОНТЕКСТА.
    - ЗАПРЕЩЕНО выдумывать факты, которых нет в базе знаний.
    - Если база знаний пуста (`Knowledge base is empty`) или информация не найдена (`No relevant information found`), НЕ ПЫТАЙСЯ продолжать поиск по этой же теме. Сразу запускай summary, чтобы он сообщил пользователю об отсутствии информации.
    - ЕСЛИ В ИСТОРИИ БОЛЕЕ 2 РАЗ ПОВТОРЯЕТСЯ СООБЩЕНИЕ ОБ ОТСУТСТВИИ РЕЗУЛЬТАТОВ ПОИСКА, ТЫ ДОЛЖЕН ПРЕКРАТИТЬ RESEARCH.

    ФОРМАТ ОТВЕТА (ТОЛЬКО JSON):
    Для следующего шага:
    {
      \"next_step\": {\"agent\": \"research\", \"task\": \"конкретное задание для агента\"},
      \"thought\": \"твое краткое рассуждение, почему выбран этот шаг. Если это исправление ошибки, укажи, ЧТО ты исправляешь.\"
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
        try {
            // ПРОВЕРКА: Если в истории уже есть [RESEARCH_FINISHED] или явные признаки отсутствия данных, то research больше не нужен.
            $hasResearchFinished = false;
            $emptyResultsCount = 0;
            $hasSummary = false;

            foreach ($state->history as $entry) {
                if (isset($entry['agent']) && $entry['agent'] === 'summary') {
                    $hasSummary = true;
                    break;
                }

                if (!isset($entry['result']) || !is_string($entry['result'])) {
                    continue;
                }

                $res = $entry['result'];
                if (str_contains($res, '[RESEARCH_FINISHED]')) {
                    $hasResearchFinished = true;
                }

                if (str_contains($res, "Knowledge base is empty") || str_contains($res, "No relevant information found")) {
                    $emptyResultsCount++;
                }
            }

            // Если summary уже выполнен, завершаем цепочку
            if ($hasSummary) {
                Log::info("DynamicPlanner: Summary уже выполнен. Завершаем цепочку.");
                return null;
            }

            // Если данные не найдены, переключаемся на summary
            if ($emptyResultsCount > 0) {
                Log::info("DynamicPlanner: Обнаружены пустые результаты поиска ({$emptyResultsCount}). Переключаемся на summary.");
                return new OrchestrationStep('summary', "Сообщи пользователю, что информация не найдена: " . $state->input);
            }

            $message = $this->buildPromptMessage($state);

            /** @var PlannerAgent $agent */
            $agent = app(PlannerAgent::class, [
                'instructions' => $this->prompt,
                'messages' => [],
                'tools' => []
            ]);

            $response = $agent->prompt($message);
            $data = $this->parseResponse((string) $response);

            if (!$data || !empty($data['finish'])) {
                return null;
            }

            if (isset($data['next_step']['agent'], $data['next_step']['task'])) {
                $suggestedAgent = $data['next_step']['agent'];

                // Если LLM предлагает research после того, как он уже помечен как завершенный
                if ($hasResearchFinished && $suggestedAgent === 'research') {
                    Log::info("DynamicPlanner: LLM предложила research повторно, несмотря на [RESEARCH_FINISHED]. Переключаемся на summary.");
                    return new OrchestrationStep('summary', "Подведи итог на основе уже собранных данных: " . $state->input);
                }

                return new OrchestrationStep(
                    $suggestedAgent,
                    $data['next_step']['task']
                );
            }

            return null;
        } catch (\Exception $e) {
            Log::error("DynamicPlanner: Ошибка планирования", ['error' => $e->getMessage()]);
            return null;
        }
    }

    private function buildPromptMessage(AgentState $state): string
    {
        $input = JsonSanitizer::sanitizeUtf8($state->input);

        $history = $this->sanitizeData($state->history);
        $historyText = json_encode($history, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        $currentContext = $state->context ?: 'Контекст пуст';
        $currentContext = is_string($currentContext) ? JsonSanitizer::sanitizeUtf8($currentContext) : $currentContext;

        return "ИСХОДНЫЙ ЗАПРОС: {$input}\n\n" .
               "ТЕКУЩИЙ КОНТЕКСТ (последний результат): {$currentContext}\n\n" .
               "ИСТОРИЯ ВЫПОЛНЕНИЯ:\n{$historyText}";
    }

    private function parseResponse(string $text): ?array
    {
        Log::info("DynamicPlanner: Ответ от LLM", ['response' => $text]);

        $jsonStart = strpos($text, '{');
        $jsonEnd = strrpos($text, '}');

        if ($jsonStart === false || $jsonEnd === false) {
            return null;
        }

        $jsonContent = substr($text, $jsonStart, $jsonEnd - $jsonStart + 1);

        // Исправление неэкранированных управляющих символов внутри JSON строк
        $jsonContent = JsonSanitizer::escapeControlCharacters($jsonContent);

        $data = json_decode($jsonContent, true);

        // Обработка случая, если SDK замокан и ответ в 'result'
        if (isset($data['result']) && is_string($data['result'])) {
            return $this->parseResponse($data['result']);
        }

        return $data;
    }

    private function sanitizeData(mixed $data): mixed
    {
        if (is_array($data)) {
            array_walk_recursive($data, function (&$item) {
                if (is_string($item)) {
                    $item = JsonSanitizer::sanitizeUtf8($item);
                }
            });
            return $data;
        }

        if (is_string($data)) {
            return JsonSanitizer::sanitizeUtf8($data);
        }

        return $data;
    }
}
