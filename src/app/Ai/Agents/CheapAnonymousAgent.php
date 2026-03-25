<?php

namespace App\Ai\Agents;

use Laravel\Ai\AnonymousAgent;
use Laravel\Ai\Attributes\MaxTokens;
use Laravel\Ai\Attributes\Timeout;
use Laravel\Ai\Attributes\UseCheapestModel;

#[UseCheapestModel]
#[MaxTokens(1000)]
#[Timeout(30)]
class CheapAnonymousAgent extends AnonymousAgent
{
}
