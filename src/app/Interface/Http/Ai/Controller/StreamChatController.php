<?php

declare(strict_types=1);

namespace App\Interface\Http\Ai\Controller;

use App\Application\Ai\Conversation\Event\StepCompleted;
use App\Application\Ai\Conversation\Event\StepPlanned;
use App\Application\Ai\Conversation\Event\WorkflowCompleted;
use App\Application\Ai\Conversation\StartConversation\StartConversationAction;
use App\Application\Ai\Conversation\StartConversation\StartConversationData;
use App\Domain\Ai\Conversation\Conversation;
use App\Domain\Ai\Conversation\SessionId;
use App\Interface\Http\Ai\Request\AiChatRequest;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

final readonly class StreamChatController
{
    public function __construct(
        private StartConversationAction $action,
    ) {}

    public function __invoke(AiChatRequest $request): StreamedResponse
    {
        $message = (string) $request->validated('message');
        $sessionId = $request->validated('session_id');

        return Response::stream(function () use ($message, $sessionId) {
            $sendEvent = static function ($type, $data) {
                $payload = ['type' => $type, 'content' => $data];
                echo 'data: ' . json_encode($payload, JSON_UNESCAPED_UNICODE) . "\n\n";
                if (ob_get_level() > 0) {
                    ob_flush();
                }
                flush();
            };

            Event::listen(StepPlanned::class, static function (StepPlanned $event) use ($sendEvent) {
                $sendEvent('step_planned', [
                    'agent' => $event->step->agent,
                    'task' => $event->step->task,
                ]);
            });

            Event::listen(StepCompleted::class, static function (StepCompleted $event) use ($sendEvent) {
                $sendEvent('step_completed', [
                    'agent' => $event->step->agent,
                    'success' => $event->success,
                    'result' => $event->result,
                ]);
            });

            Event::listen(WorkflowCompleted::class, static function (WorkflowCompleted $event) use ($sendEvent) {
                $last = $event->conversation->lastEntry();
                $sendEvent('final_result', $last?->result ?? 'Workflow completed');
                echo "data: [DONE]\n\n";
            });

            $conversation = Conversation::start(
                $message,
                $sessionId !== null && $sessionId !== '' ? SessionId::fromString($sessionId) : null,
            );

            $sendEvent('session_id', $conversation->sessionId->value);

            echo ":\n\n";
            if (ob_get_level() > 0) {
                ob_flush();
            }
            flush();

            $this->action->handle(new StartConversationData(
                message: $message,
                sessionId: $conversation->sessionId->value,
            ));
        }, 200, [
            'Cache-Control' => 'no-cache',
            'Content-Type' => 'text/event-stream',
            'X-Accel-Buffering' => 'no',
        ]);
    }
}
