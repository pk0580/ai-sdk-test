<?php

namespace Tests\Feature;

use App\Ai\Events\Workflow\StepRequested;
use App\Ai\Events\Workflow\WorkflowCompleted;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class CancelWorkflowTest extends TestCase
{
    public function test_workflow_can_be_cancelled()
    {
        Event::fake([WorkflowCompleted::class]);

        $sessionId = 'test_session_123';

        // 1. Помечаем сессию как отмененную
        $this->post('/cancel', ['session_id' => $sessionId])
             ->assertStatus(200)
             ->assertJson(['message' => "Запрос {$sessionId} отменен"]);

        $this->assertTrue(Cache::has("cancel_{$sessionId}"));

        // 2. Запускаем процесс через Supervisor (через контроллер)
        // Мы не фейкаем StepRequested, чтобы PlanNextStepListener сработал
        $this->post('/chat', [
            'message' => 'Test message',
            'session_id' => $sessionId
        ])->assertStatus(200);

        // Проверяем, что WorkflowCompleted был вызван (из-за отмены в PlanNextStepListener)
        Event::assertDispatched(WorkflowCompleted::class, function ($event) use ($sessionId) {
            return $event->state->sessionId === $sessionId;
        });
    }

    public function test_cancel_without_session_id_returns_error()
    {
        $this->post('/cancel', [])
             ->assertStatus(400)
             ->assertJson(['error' => 'Session ID не указан']);
    }
}
