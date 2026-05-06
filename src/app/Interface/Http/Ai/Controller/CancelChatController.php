<?php

declare(strict_types=1);

namespace App\Interface\Http\Ai\Controller;

use App\Application\Ai\Conversation\CancelConversation\CancelConversationAction;
use App\Application\Ai\Conversation\CancelConversation\CancelConversationData;
use App\Interface\Http\Ai\Request\AiCancelRequest;
use Illuminate\Http\JsonResponse;

final readonly class CancelChatController
{
    public function __construct(
        private CancelConversationAction $action,
    ) {}

    public function __invoke(AiCancelRequest $request): JsonResponse
    {
        $sessionId = (string) $request->validated('session_id');

        $this->action->handle(new CancelConversationData($sessionId));

        return response()->json([
            'message' => "Запрос {$sessionId} отменен",
        ]);
    }
}
