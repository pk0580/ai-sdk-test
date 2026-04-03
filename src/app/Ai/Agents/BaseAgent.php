<?php

namespace App\Ai\Agents;

use App\Ai\Core\State\AgentState;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Promptable;
use Stringable;
use Illuminate\Contracts\JsonSchema\JsonSchema;

abstract class BaseAgent implements Agent, Conversational, HasTools
{
    use Promptable;

    protected string $name;
    protected string $systemPrompt;

    public function __construct(string $name, string $systemPrompt)
    {
        $this->name = $name;
        $this->systemPrompt = $systemPrompt;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function instructions(): Stringable|string
    {
        return $this->systemPrompt;
    }

    public function messages(): iterable
    {
        return [];
    }

    public function tools(): iterable
    {
        return [];
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }

    public function ask(string $message, ?string $sessionId = null): string
    {
        Log::info("Agent [{$this->name}]: Обработка сообщения", ['message' => $message]);

        try {
            // Если агент имеет инструменты, залогируем их
            if ($this instanceof HasTools) {
                $tools = $this->tools();
                if (!empty($tools)) {
                    $toolNames = [];
                    foreach ($tools as $name => $tool) {
                        $toolNames[] = is_string($name) ? $name : get_class($tool);
                    }
                    Log::info("Agent [{$this->name}]: Доступные инструменты: " . implode(', ', $toolNames));
                }
            }

            $response = $this->prompt($message);

            return (string) $response;
        } catch (\Exception $e) {
            Log::error("Agent [{$this->name}]: Ошибка", ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return "Извините, агент {$this->name} столкнулся с ошибкой: " . $e->getMessage();
        }
    }

    /**
     * Позволяет агенту выполнять задачи с использованием LoopController.
     * По умолчанию, агенты могут иметь разный набор инструментов или промптов.
     */
    abstract public function execute(string $task, AgentState $state): string;
}
