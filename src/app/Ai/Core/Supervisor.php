<?php

namespace App\Ai\Core;

use App\Ai\Agents\BaseAgent;
use App\Ai\Agents\ResearchAgent;
use App\Ai\Agents\SummaryAgent;
use App\Ai\Events\SupervisorDecisionMade;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\AnonymousAgent;

class Supervisor
{
    /** @var array<string, BaseAgent> */
    private array $agents = [];

    public function __construct(ResearchAgent $researchAgent, SummaryAgent $summaryAgent)
    {
        $this->agents['research'] = $researchAgent;
        $this->agents['summary'] = $summaryAgent;
    }

    public function handle(string $userMessage): string
    {
        Log::info("Supervisor: Получен запрос", ['message' => $userMessage]);

        // Решаем, нужен ли нам один агент или цепочка
        // Для простоты MVP, Supervisor сам решает через LLM, каких агентов вызвать и в каком порядке.

        $decision = $this->makeDecision($userMessage);

        SupervisorDecisionMade::dispatch($decision, $userMessage);

        Log::info("Supervisor: Решение принято", ['decision' => $decision]);

        if ($decision['type'] === 'single') {
            $agentName = $decision['agent'];
            if (isset($this->agents[$agentName])) {
                return $this->agents[$agentName]->execute($userMessage);
            }
        }

        if ($decision['type'] === 'chain') {
            $result = $userMessage;
            foreach ($decision['agents'] as $step) {
                $agentName = $step['agent'];
                $task = $step['task'] ?? $result;

                Log::info("Supervisor: Вызов агента в цепочке", ['agent' => $agentName]);

                if (isset($this->agents[$agentName])) {
                    $result = $this->agents[$agentName]->execute($task);
                }
            }
            return $result;
        }

        // Fallback: просто используем LoopController напрямую (через ResearchAgent по умолчанию)
        return $this->agents['research']->execute($userMessage);
    }

    private function makeDecision(string $message): array
    {
        // Простой эвристический выбор для детерминированных тестов и быстрого MVP
        $lower = mb_strtolower($message);
        if (str_contains($lower, 'summarize') || str_contains($lower, 'резюме') || (str_contains($lower, 'кратк') && !str_contains($lower, 'правила'))) {
            return [
                'type' => 'chain',
                'agents' => [
                    ['agent' => 'research', 'task' => $message],
                    ['agent' => 'summary', 'task' => 'Сделай краткое резюме полученных данных']
                ]
            ];
        }

        // Иначе пробуем спросить LLM как fallback
        $prompt = "Ты — Диспетчер (Supervisor) в мульти-агентной системе.
        Твоя задача — проанализировать запрос и решить, какие агенты должны его обработать.

        Доступные агенты:
        1. research — ищет информацию, проводит расчеты.
        2. summary — делает краткий вывод, резюмирует информацию.

        Ты можешь выбрать одного агента (single) или цепочку (chain).
        Например, для 'Собери инфу про X и дай выводы' -> цепочка [research, summary].
        Для 'Какая погода?' -> research.

        Ответ в формате JSON:
        {
          \"type\": \"single\" | \"chain\",
          \"agent\": \"research\" | \"summary\" (только для single),
          \"agents\": [
            {\"agent\": \"research\", \"task\": \"исходная или уточненная задача\"},
            {\"agent\": \"summary\", \"task\": \"что именно резюмировать\"}
          ] (только для chain)
        }";

        try {
            $agent = new AnonymousAgent($prompt, [], []);
            $response = $agent->prompt(
                $message,
                provider: 'ollama',
                model: config('ai.providers.ollama.models.text.smartest')
            );
            $text = (string) $response;

            $jsonStart = strpos($text, '{');
            $jsonEnd = strrpos($text, '}');
            $jsonContent = substr($text, $jsonStart, $jsonEnd - $jsonStart + 1);

            return json_decode($jsonContent, true) ?? ['type' => 'single', 'agent' => 'research'];
        } catch (\Exception $e) {
            return ['type' => 'single', 'agent' => 'research'];
        }
    }
}
