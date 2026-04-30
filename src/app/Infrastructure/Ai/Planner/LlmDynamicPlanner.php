<?php

declare(strict_types=1);

namespace App\Infrastructure\Ai\Planner;

use App\Domain\Ai\Conversation\AgentName;
use App\Domain\Ai\Conversation\Conversation;
use App\Domain\Ai\Conversation\PlannerInterface;
use App\Domain\Ai\Conversation\PlanStep;
use App\Infrastructure\Ai\Agent\PlannerAgent;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\Log;

final class LlmDynamicPlanner implements PlannerInterface
{
    public const int HISTORY_LIMIT = 5;

    private const string PROMPT = "Ты — Динамический Планировщик.
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

    public function __construct(private readonly Container $container) {}

    public function decideNextStep(Conversation $conversation): ?PlanStep
    {
        try {
            if ($conversation->history === []) {
                return new PlanStep(AgentName::RESEARCH, $conversation->input);
            }

            $last = $conversation->lastEntry();

            if ($last !== null && $last->agent === AgentName::SUMMARY && $last->success) {
                return null;
            }

            if ($last !== null && $last->agent === AgentName::RESEARCH && $last->success) {
                if (
                    str_contains($last->result, '[RESEARCH_FINISHED]')
                    || $conversation->historyCount() >= self::HISTORY_LIMIT
                ) {
                    return new PlanStep(AgentName::SUMMARY, 'Подведи итог: ' . $conversation->input);
                }
            }

            $message = "Запрос: {$conversation->input}\nИстория: " . json_encode($conversation->historyAsArrays());
            $response = $this->askPlanner($message);
            $data = $this->parseResponse($response);

            if ($data === null || !empty($data['finish'])) {
                return null;
            }

            return new PlanStep(
                $data['next_step']['agent'],
                $data['next_step']['task'],
            );
        } catch (\Throwable $e) {
            Log::error('DynamicPlanner Error: ' . $e->getMessage());
            return null;
        }
    }

    private function askPlanner(string $message): string
    {
        $agent = $this->container->make(PlannerAgent::class, [
            'instructions' => self::PROMPT,
        ]);

        return (string) $agent->prompt($message);
    }

    private function parseResponse(string $text): ?array
    {
        $start = strpos($text, '{');
        $end = strrpos($text, '}');

        if ($start === false || $end === false) {
            return null;
        }

        $data = json_decode(substr($text, $start, $end - $start + 1), true);

        return is_array($data) ? $data : null;
    }
}
