<?php

namespace App\Ai\Agents;

use Laravel\Ai\AnonymousAgent;
use Laravel\Ai\Attributes\MaxTokens;
use Laravel\Ai\Attributes\Timeout;

#[MaxTokens(1500)]
#[Timeout(300)]
class PlannerAgent extends AnonymousAgent
{
    public function model(): string
    {
        $defaultProvider = config('ai.default');
        return config("ai.providers.{$defaultProvider}.models.text.smartest")
            ?? config("ai.providers.{$defaultProvider}.model")
            ?? 'gpt-4o';
    }
}
