<?php

declare(strict_types=1);

namespace App\Infrastructure\Ai\Agent;

use Laravel\Ai\AnonymousAgent;
use Laravel\Ai\Attributes\MaxTokens;
use Laravel\Ai\Attributes\Timeout;
use Laravel\Ai\Attributes\UseSmartestModel;

#[UseSmartestModel]
#[MaxTokens(4000)]
#[Timeout(600)]
class SmartAnonymousAgent extends AnonymousAgent
{
    public function __construct(string $instructions = '', iterable $messages = [], iterable $tools = [])
    {
        parent::__construct($instructions, $messages, $tools);
    }
}
