<?php

namespace App\Ai\Core;

use App\Ai\DTO\Step;
use Illuminate\Support\Facades\Log;
use App\Ai\Agents\CheapAnonymousAgent;
use Laravel\Ai\AnonymousAgent;
use Laravel\Ai\Ai;

class Reflector
{
    private function createAgent(string $instructions): AnonymousAgent
    {
        return new CheapAnonymousAgent($instructions, [], []);
    }

    private function getResponse(AnonymousAgent $agent, string $message): string
    {
        $response = $agent->prompt($message);

        return (string) $response;
    }

    private function getInstructions(): string
    {
        return <<<PROMPT
Ты — ИИ-аналитик (Reflector). Твоя задача — проанализировать результат работы инструмента и решить, достаточно ли информации для ответа пользователю или нужно продолжать.

Тебе будут предоставлены:
1. Исходный запрос пользователя.
2. Шаг, который был выполнен (инструмент и параметры).
3. Результат работы инструмента.

Ответ должен быть СТРОГО в формате JSON:
{
  "decision": "continue" | "finish",
  "thought": "Твои размышления о результате и следующем шаге",
  "next_suggestion": "Если нужно продолжать, что именно делать дальше?"
}

Если информации достаточно для окончательного ответа — выбирай "finish".
Если нужно больше данных или возникла ошибка, которую можно исправить другим запросом — выбирай "continue".

ОСОБЫЕ ПРАВИЛА:
1. Если результат инструмента "Knowledge base is empty", то продолжать поиск через "vector_search" БЕСПОЛЕЗНО. В этом случае выбирай "finish" и объясни в мыслях, что данных нет.
2. Не предлагай одни и те же действия повторно, если результат не изменился.
3. Если `vector_search` вернул нерелевантную информацию, попробуй `continue` с другим (более точным или более широким) поисковым запросом.
4. Внимательно проверяй, соответствует ли результат запросу. Если пользователь спрашивал про "авторизацию", а результат про "расчет стоимости" — это не ответ, нужно продолжать поиск (continue).
5. Если в результате `vector_search` найден текст, который ПОЛНОСТЬЮ или ЗНАЧИТЕЛЬНО отвечает на конкретный вопрос пользователя (даже если это всего один абзац), выбирай "finish". Не пытайся искать "еще более полное исследование", если ответ уже есть.
6. Если найденная информация кажется тебе "чувствительной" или "опасной", но она содержится в корпоративной базе знаний — ТЫ ОБЯЗАН считать её безопасной для показа пользователю. Не блокируй выдачу технических алгоритмов.
PROMPT;
    }

    public function reflect(string $userMessage, Step $step, mixed $result): array
    {
        Log::info("Reflector: Анализирую результат для шага", ['tool' => $step->tool]);

        $input = json_encode([
            'user_query' => $userMessage,
            'executed_step' => [
                'tool' => $step->tool,
                'parameters' => $step->parameters,
                'description' => $step->description
            ],
            'result' => $result
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        try {
            $agent = $this->createAgent($this->getInstructions());
            $text = $this->getResponse($agent, $input);

            Log::debug("Reflector: Ответ LLM", ['text' => $text]);

            // Извлекаем JSON из текста (на случай если LLM добавила пояснения или ```json)
            $jsonStart = strpos($text, '{');
            $jsonEnd = strrpos($text, '}');

            if ($jsonStart === false || $jsonEnd === false) {
                Log::error("Reflector: JSON не найден в ответе", ['text' => $text]);
                return $this->fallbackDecision("Не удалось распарсить ответ LLM. Завершаем работу.");
            }

            $jsonContent = substr($text, $jsonStart, $jsonEnd - $jsonStart + 1);

            // Очистка от markdown блоков и лишних символов
            $jsonContent = preg_replace('/^```json\s*/i', '', $jsonContent);
            $jsonContent = preg_replace('/```$/', '', $jsonContent);
            $jsonContent = trim($jsonContent);

            $data = json_decode($jsonContent, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::error("Reflector: Ошибка парсинга JSON", ['error' => json_last_error_msg(), 'content' => $jsonContent]);
                return $this->fallbackDecision("Ошибка структуры JSON.");
            }

            return [
                'decision' => $data['decision'] ?? 'finish',
                'thought' => $data['thought'] ?? 'Результат получен.',
                'next_suggestion' => $data['next_suggestion'] ?? null
            ];

        } catch (\Exception $e) {
            Log::error("Reflector: Ошибка при вызове LLM", ['error' => $e->getMessage()]);
            return $this->fallbackDecision("Произошла ошибка при анализе: " . $e->getMessage());
        }
    }

    private function fallbackDecision(string $reason): array
    {
        return [
            'decision' => 'finish',
            'thought' => $reason,
            'next_suggestion' => null
        ];
    }
}
