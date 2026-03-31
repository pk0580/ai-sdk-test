<?php

namespace App\Ai\Agents;

use Laravel\Ai\AnonymousAgent;
use Laravel\Ai\Attributes\MaxTokens;
use Laravel\Ai\Attributes\Timeout;
// use Laravel\Ai\Attributes\UseCheapestModel;

// #[UseCheapestModel] для локального Ollama не работает
#[MaxTokens(1000)]
#[Timeout(600)]
class CheapAnonymousAgent extends AnonymousAgent
{
    public function __construct(string $instructions = '', iterable $messages = [], iterable $tools = [])
    {
        parent::__construct($instructions, $messages, $tools);
    }

    public function model(): string
    {
        $defaultProvider = config('ai.default');
        return config("ai.providers.{$defaultProvider}.models.text.cheapest")
            ?? config("ai.providers.{$defaultProvider}.model") // Для OpenAI/других где нет иерархии text.cheapest
            ?? 'llama3.1:latest'; // Фоллбек
    }
}
