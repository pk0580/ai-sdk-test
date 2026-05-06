<?php

declare(strict_types=1);

namespace App\Interface\Http\Ai\Controller;

use App\Infrastructure\Ai\Agent\ResearchAgent;
use App\Interface\Http\Ai\Request\AiChatRequest;
use Illuminate\Http\JsonResponse;

final class BroadcastChatController
{
    public function __invoke(AiChatRequest $request): JsonResponse
    {
        $message = (string) $request->validated('message');
        $agent = app(ResearchAgent::class);

        $agent->broadcastNow($message, ['ai-chat']);

        return response()->json(['message' => 'Вещание (ResearchAgent) запущено на канале ai-chat']);
    }
}
