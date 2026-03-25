<?php

namespace App\Ai\Agents;

use Laravel\Ai\AnonymousAgent;
use Laravel\Ai\Attributes\MaxTokens;
use Laravel\Ai\Attributes\Timeout;
// use Laravel\Ai\Attributes\UseCheapestModel;

// #[UseCheapestModel] для локального Ollama не работает
#[MaxTokens(1000)]
#[Timeout(300)]
class CheapAnonymousAgent extends AnonymousAgent
{
    public function model(): string
    {
        return config('ai.providers.ollama.models.text.cheapest');
    }
}
