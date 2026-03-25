<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Ai\Agents\SummaryAgent;
use Illuminate\Support\Facades\Queue;
use Laravel\Ai\QueuedAgentPrompt;

class AiFrontendTest extends TestCase
{
    /** @test */
    public function index_page_is_accessible()
    {
        $response = $this->get('/');
        $response->assertStatus(200);
        $response->assertSee('AI SDK Testing');
    }

    /** @test */
    public function chat_method_returns_json()
    {
        // Мокаем ответ агента, если это возможно, но здесь проверим просто доступность
        // Так как это тест интеграции с SDK, может потребоваться реальный ключ или мок SDK
        $response = $this->post('/chat', ['message' => 'Hello']);
        $response->assertStatus(200);
        $response->assertJsonStructure(['response']);
    }

    /** @test */
    public function stream_method_returns_streamed_response()
    {
        $response = $this->get('/stream?message=Hello');
        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/event-stream; charset=UTF-8');
    }

    /** @test */
    public function queue_method_dispatches_job()
    {
        // Laravel AI SDK использует свои механизмы для очередей
        $response = $this->post('/queue', ['message' => 'Hello']);
        $response->assertStatus(200);
        $response->assertJsonStructure(['message', 'job_id']);
    }
}
