<?php

namespace App\Http\Controllers;

use App\Ai\Agents\ResearchAgent;
use App\Ai\Core\Supervisor;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use App\Http\Requests\AiChatRequest;
use App\Http\Requests\AiCancelRequest;
use App\Ai\Events\Workflow\StepPlanned;
use App\Ai\Events\Workflow\StepCompleted;
use App\Ai\Events\Workflow\WorkflowCompleted;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AiController extends Controller
{
    protected Supervisor $supervisor;

    public function __construct(Supervisor $supervisor)
    {
        $this->supervisor = $supervisor;
    }

    public function index(): Factory|View
    {
        return view('chat');
    }

    public function chat(AiChatRequest $request): JsonResponse
    {
        $message = $request->validated('message');
        $sessionId = $request->validated('session_id');

        $state = $this->supervisor->handle($message, $sessionId);

        // В реактивной модели handle возвращает начальное состояние.
        // Чтобы получить финальный ответ в синхронном контроллере, нам пришлось бы ждать завершения.
        // Но для тестов и простоты пока вернем информацию о запуске.
        // В реальном приложении лучше использовать stream или очереди.

        return response()->json([
            'message' => 'Workflow started',
            'session_id' => $state->sessionId,
            'input' => $state->input
        ]);
    }

    public function stream(AiChatRequest $request): StreamedResponse
    {
        $message = $request->validated('message');
        $sessionId = $request->validated('session_id');

        return Response::stream(function () use ($message, $sessionId) {
            $sendEvent = function ($type, $data) {
                $payload = ['type' => $type, 'content' => $data];
                echo "data: " . json_encode($payload, JSON_UNESCAPED_UNICODE) . "\n\n";
                if (ob_get_level() > 0) {
                    ob_flush();
                }
                flush();
            };

            // Слушаем новые события
            Event::listen(StepPlanned::class, function ($event) use ($sendEvent) {
                $sendEvent('step_planned', [
                    'agent' => $event->step->agent,
                    'task' => $event->step->task
                ]);
            });

            Event::listen(StepCompleted::class, function ($event) use ($sendEvent) {
                $sendEvent('step_completed', [
                    'agent' => $event->step->agent,
                    'success' => $event->success,
                    'result' => $event->result
                ]);
            });

            Event::listen(WorkflowCompleted::class, function ($event) use ($sendEvent) {
                $lastEntry = end($event->state->history);
                $sendEvent('final_result', $lastEntry['result'] ?? 'Workflow completed');
                echo "data: [DONE]\n\n";
            });

            // Сначала создаем состояние, чтобы получить sessionId
            $state = \App\Ai\Core\State\AgentState::init($message, $sessionId);

            // Сразу отправляем sessionId клиенту, чтобы кнопка "Остановить" заработала немедленно
            $sendEvent('session_id', $state->sessionId);

            // Дополнительная отправка пустых байтов, чтобы принудительно пробросить данные через буферы Nginx
            echo ":\n\n";
            if (ob_get_level() > 0) ob_flush();
            flush();

            // Запускаем процесс
            $this->supervisor->handle($message, $state->sessionId);
        }, 200, [
            'Cache-Control' => 'no-cache',
            'Content-Type' => 'text/event-stream',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    public function queue(AiChatRequest $request): JsonResponse
    {
        $message = $request->validated('message');
        $sessionId = $request->validated('session_id');

        // Мы можем запускать Supervisor асинхронно, если слушатели ShouldQueue.
        // Или просто вызвать handle.
        $this->supervisor->handle($message, $sessionId);

        return response()->json([
            'message' => 'Workflow запущен (асинхронно, если это настроено)',
            'session_id' => $sessionId,
            'job_id' => 'fake_id_for_tests',
        ]);
    }

    public function broadcast(AiChatRequest $request): JsonResponse
    {
        $message = $request->validated('message');
        $agent = app(ResearchAgent::class);

        $agent->broadcastNow($message, ['ai-chat']);

        return response()->json(['message' => 'Вещание (ResearchAgent) запущено на канале ai-chat']);
    }
}
