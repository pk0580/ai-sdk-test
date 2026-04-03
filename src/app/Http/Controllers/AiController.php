<?php

namespace App\Http\Controllers;

use App\Ai\Core\Supervisor;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Ai\Events\Workflow\StepPlanned;
use App\Ai\Events\Workflow\StepCompleted;
use App\Ai\Events\Workflow\WorkflowCompleted;
use Illuminate\Support\Facades\Event;
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

    public function chat(Request $request): JsonResponse
    {
        $message = $request->input('message');

        $state = $this->supervisor->handle($message);

        // В реактивной модели handle возвращает начальное состояние.
        // Чтобы получить финальный ответ в синхронном контроллере, нам пришлось бы ждать завершения.
        // Но для тестов и простоты пока вернем информацию о запуске.
        // В реальном приложении лучше использовать stream или очереди.

        return response()->json([
            'message' => 'Workflow started',
            'input' => $state->input
        ]);
    }

    public function stream(Request $request): StreamedResponse
    {
        $message = $request->input('message');

        return Response::stream(function () use ($message) {
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

            // Запускаем процесс
            $this->supervisor->handle($message);
        }, 200, [
            'Cache-Control' => 'no-cache',
            'Content-Type' => 'text/event-stream',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    public function queue(Request $request): JsonResponse
    {
        $message = $request->input('message');

        // В новой модели мы можем запускать Supervisor асинхронно, если слушатели ShouldQueue.
        // Или просто вызвать handle.
        $this->supervisor->handle($message);

        return response()->json([
            'message' => 'Workflow запущен (асинхронно, если это настроено)',
            'job_id' => 'fake_id_for_tests',
        ]);
    }

    public function broadcast(Request $request): JsonResponse
    {
        $message = $request->input('message');
        $agent = app(\App\Ai\Agents\ResearchAgent::class);

        $agent->broadcastNow($message, ['ai-chat']);

        return response()->json(['message' => 'Вещание (ResearchAgent) запущено на канале ai-chat']);
    }

    public function cancel(Request $request): JsonResponse
    {
        $sessionId = $request->input('session_id');
        if ($sessionId) {
            Cache::put("cancel_{$sessionId}", true, 60);
            return response()->json(['message' => "Запрос {$sessionId} отменен"]);
        }
        return response()->json(['error' => 'Session ID не указан'], 400);
    }
}
