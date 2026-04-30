<?php

declare(strict_types=1);

namespace App\Infrastructure\Ai\Agent;

use App\Domain\Ai\Conversation\Conversation;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Promptable;
use Stringable;

abstract class BaseAgent implements Agent, Conversational, HasTools
{
    use Promptable;

    public function __construct(
        protected readonly string $name,
        protected readonly string $systemPrompt,
    ) {}

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

    public function ask(string $message): string
    {
        Log::info("Agent [{$this->name}]: handling message", ['message' => $message]);

        try {
            return (string) $this->prompt($message);
        } catch (\Throwable $e) {
            Log::error("Agent [{$this->name}]: error", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return "Извините, агент {$this->name} столкнулся с ошибкой: " . $e->getMessage();
        }
    }

    abstract public function execute(string $task, Conversation $conversation): string;
}
