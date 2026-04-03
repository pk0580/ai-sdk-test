<?php

namespace Tests\Feature;

use App\Ai\Events\Workflow\StepPlanned;
use App\Ai\Events\Workflow\WorkflowCompleted;
use Tests\TestCase;
use App\Ai\Agents\SummaryAgent;
use App\Ai\Agents\CheapAnonymousAgent;
use App\Ai\Agents\SmartAnonymousAgent;
use App\Ai\Agents\PlannerAgent;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Event;
use Laravel\Ai\QueuedAgentPrompt;

class AiFrontendTest extends TestCase
{
    public function test_index_page_is_accessible(): void
    {
        $response = $this->get('/');
        $response->assertStatus(200);
        $response->assertSee('AI SDK Testing');
    }

    public function test_chat_method_returns_json(): void
    {
        Event::fake();
        $response = $this->post('/chat', ['message' => 'Hello', 'session_id' => 'test_123']);
        $response->assertStatus(200);
        $response->assertJsonStructure(['message', 'input', 'session_id']);
        $this->assertEquals('Hello', $response->json('input'));
        $this->assertEquals('test_123', $response->json('session_id'));
    }

    public function test_stream_method_returns_streamed_response(): void
    {
        Event::fake();
        $response = $this->get('/stream?message=Hello');
        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/event-stream; charset=UTF-8');
    }

    public function test_queue_method_dispatches_job(): void
    {
        Event::fake();
        $response = $this->post('/queue', ['message' => 'Hello']);
        $response->assertStatus(200);
        $response->assertJsonStructure(['message', 'job_id']);
    }

    public function test_chat_validation_fails_without_message(): void
    {
        $response = $this->postJson('/chat', ['session_id' => 'test_123']);
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['message']);
    }
}
