<?php

declare(strict_types=1);

namespace App\Infrastructure\Ai\Agent;

use Laravel\Ai\AnonymousAgent;
use Laravel\Ai\Attributes\MaxTokens;
use Laravel\Ai\Attributes\Timeout;

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
            ?? config("ai.providers.{$defaultProvider}.model")
            ?? 'llama3.1:latest';
    }
}
