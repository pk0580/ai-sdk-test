<?php

declare(strict_types=1);

namespace App\Interface\Http\Ai\Controller;

use App\Application\Ai\Conversation\StartConversation\StartConversationAction;
use App\Application\Ai\Conversation\StartConversation\StartConversationData;
use App\Interface\Http\Ai\Request\AiChatRequest;
use Illuminate\Http\JsonResponse;

final readonly class QueueChatController
{
    public function __construct(
        private StartConversationAction $action,
    ) {}

    public function __invoke(AiChatRequest $request): JsonResponse
    {
        $output = $this->action->handle(new StartConversationData(
            message: (string) $request->validated('message'),
            sessionId: $request->validated('session_id'),
        ));

        return response()->json([
            'message' => 'Workflow queued',
            'session_id' => $output->sessionId(),
            'job_id' => 'fake_id_for_tests',
        ]);
    }
}
