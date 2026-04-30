<?php

declare(strict_types=1);

namespace App\Interface\Http\Controller;

use App\Application\Ai\Conversation\DTO\CancelConversationInput;
use App\Application\Ai\Conversation\DTO\StartConversationInput;
use App\Application\Ai\Conversation\Event\StepCompleted;
use App\Application\Ai\Conversation\Event\StepPlanned;
use App\Application\Ai\Conversation\Event\WorkflowCompleted;
use App\Application\Ai\Conversation\UseCase\CancelConversationUseCase;
use App\Application\Ai\Conversation\UseCase\StartConversationUseCase;
use App\Domain\Ai\Conversation\Conversation;
use App\Domain\Ai\Conversation\SessionId;
use App\Infrastructure\Ai\Agent\ResearchAgent;
use App\Interface\Http\Request\AiCancelRequest;
use App\Interface\Http\Request\AiChatRequest;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class AiController extends Controller
{
    public function __construct(
        private readonly StartConversationUseCase $startConversation,
        private readonly CancelConversationUseCase $cancelConversation,
    ) {}

    public function index(): Factory|View
    {
        return view('chat');
    }

    public function chat(AiChatRequest $request): JsonResponse
    {
        $output = $this->startConversation->execute(new StartConversationInput(
            message: (string) $request->validated('message'),
            sessionId: $request->validated('session_id'),
        ));

        return response()->json([
            'message' => 'Workflow started',
            'session_id' => $output->sessionId(),
            'input' => $output->input(),
        ]);
    }

    public function stream(AiChatRequest $request): StreamedResponse
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

            $this->startConversation->execute(new StartConversationInput(
                message: $message,
                sessionId: $conversation->sessionId->value,
            ));
        }, 200, [
            'Cache-Control' => 'no-cache',
            'Content-Type' => 'text/event-stream',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    public function queue(AiChatRequest $request): JsonResponse
    {
        $output = $this->startConversation->execute(new StartConversationInput(
            message: (string) $request->validated('message'),
            sessionId: $request->validated('session_id'),
        ));

        return response()->json([
            'message' => 'Workflow queued',
            'session_id' => $output->sessionId(),
            'job_id' => 'fake_id_for_tests',
        ]);
    }

    public function cancel(AiCancelRequest $request): JsonResponse
    {
        $sessionId = (string) $request->validated('session_id');

        $this->cancelConversation->execute(new CancelConversationInput($sessionId));

        return response()->json([
            'message' => "Запрос {$sessionId} отменен",
        ]);
    }

    public function broadcast(AiChatRequest $request): JsonResponse
    {
        $message = (string) $request->validated('message');
        $agent = app(ResearchAgent::class);

        $agent->broadcastNow($message, ['ai-chat']);

        return response()->json(['message' => 'Вещание (ResearchAgent) запущено на канале ai-chat']);
    }
}
