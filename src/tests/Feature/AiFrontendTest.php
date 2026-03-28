<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Ai\Agents\SummaryAgent;
use Illuminate\Support\Facades\Queue;
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
        $response = $this->post('/chat', ['message' => 'Hello']);
        $response->assertStatus(200);
        $response->assertJsonStructure(['response']);
    }

    public function test_stream_method_returns_streamed_response(): void
    {
        $response = $this->get('/stream?message=Hello');
        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/event-stream; charset=UTF-8');
    }

    public function test_queue_method_dispatches_job(): void
    {
        $response = $this->post('/queue', ['message' => 'Hello']);
        $response->assertStatus(200);
        $response->assertJsonStructure(['message', 'job_id']);
    }
}
