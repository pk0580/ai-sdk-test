<?php

namespace App\Http\Controllers;

use App\Ai\Core\Supervisor;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Ai\Events\PlanCreated;
use App\Ai\Events\ReflectionGenerated;
use App\Ai\Events\StepCompleted;
use App\Ai\Events\SupervisorDecisionMade;
use App\Ai\Events\ToolCalled;
use App\Ai\Events\ToolResultReceived;
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

        $response = $this->supervisor->handle($message);

        return response()->json(['response' => $response->context ?? 'Ответ не сформирован']);
    }

    public function stream(Request $request): StreamedResponse
    {
        $message = $request->input('message');

        return Response::stream(function () use ($message) {
            $sessionId = null;

            $sendEvent = function ($type, $data) use (&$sessionId) {
                $payload = ['type' => $type, 'content' => $data];
                if ($sessionId) {
                    $payload['session_id'] = $sessionId;
                }
                echo "data: " . json_encode($payload, JSON_UNESCAPED_UNICODE) . "\n\n";
                if (ob_get_level() > 0) {
                    ob_flush();
                }
                flush();
            };

            // Слушаем события и отправляем их в поток
            Event::listen(SupervisorDecisionMade::class, function ($event) use ($sendEvent) {
                $sendEvent('supervisor_decision', $event->decision);
            });

            Event::listen(PlanCreated::class, function ($event) use ($sendEvent, &$sessionId) {
                $sessionId = $event->options['session_id'] ?? null;
                $sendEvent('plan_created', $event->plan->toArray());
            });

            Event::listen(ToolCalled::class, function ($event) use ($sendEvent) {
                $sendEvent('tool_called', ['tool' => $event->step->tool, 'params' => $event->step->parameters]);
            });

            Event::listen(ToolResultReceived::class, function ($event) use ($sendEvent) {
                $sendEvent('tool_result', $event->result);
            });

            Event::listen(ReflectionGenerated::class, function ($event) use ($sendEvent) {
                $sendEvent('reflection', [
                    'decision' => $event->decision,
                    'thought' => $event->thought,
                ]);
            });

            // Запускаем процесс
            $response = $this->supervisor->handle($message);

            // Финальный ответ
            $sendEvent('final_result', $response->context ?? 'Ответ не сформирован');
            echo "data: [DONE]\n\n";
        }, 200, [
            'Cache-Control' => 'no-cache',
            'Content-Type' => 'text/event-stream',
            'X-Accel-Buffering' => 'no', // Для Nginx
        ]);
    }

    public function queue(Request $request): JsonResponse
    {
        $message = $request->input('message');

        // Для очереди используем ResearchAgent, так как он запускает основной цикл
        $agent = app(\App\Ai\Agents\ResearchAgent::class);
        $agent->queue($message);

        return response()->json([
            'message' => 'Запрос (ResearchAgent) поставлен в очередь',
            'job_id' => 'queued' // AI SDK job objects might not have id() in this version
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
