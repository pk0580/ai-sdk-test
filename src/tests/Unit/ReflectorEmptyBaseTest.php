<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Ai\Core\Reflector;
use App\Ai\Tools\ToolRegistry;
use App\Ai\Agents\CheapAnonymousAgent;
use App\Ai\DTO\Step;
use Illuminate\Support\Facades\Log;

class ReflectorEmptyBaseTest extends TestCase
{
    public function test_reflector_finishes_when_knowledge_base_is_empty()
    {
        $toolRegistry = new ToolRegistry();
        // В реальном окружении VectorSearchTool возвращает именно эту строку при пустой базе
        $emptyBaseMessage = "Knowledge base is empty. No documents have been indexed yet.";

        $batchResults = [
            [
                'step' => new Step('vector_search', ['query' => 'test query'], 'Search in DB'),
                'result' => $emptyBaseMessage
            ]
        ];

        // Фейкаем ответ LLM, который ОШИБОЧНО решает продолжить
        // Нам нужно проверить, что если мы изменим промпт, ИИ будет склоняться к finish
        // Но сначала проверим текущее поведение (без фейка, если возможно, или с фейком для эмуляции проблемы)

        // Для воспроизведения проблемы мы можем либо запустить РЕАЛЬНЫЙ ИИ (если ключи есть),
        // либо зафейкать его "плохой" ответ и посмотреть, как Reflector его обрабатывает,
        // но задача скорее в том, чтобы ИЗМЕНИТЬ промпт так, чтобы ИИ в ПРИНЦИПЕ не давал "плохой" ответ.

        // В данном тесте мы проверим, что Reflector правильно ПЕРЕДАЕТ инструкции,
        // но так как мы не можем легко проверить "внутренности" промпта без вызова LLM,
        // мы зафейкаем ответ, который имитирует ошибку, и убедимся, что мы можем его поправить.

        CheapAnonymousAgent::fake([
            json_encode([
                'decision' => 'continue', // ОШИБОЧНОЕ решение, которое мы хотим предотвратить
                'thought' => 'Knowledge base is empty, but I want to try again.',
                'next_suggestion' => 'try again'
            ])
        ]);

        $reflector = new Reflector($toolRegistry);
        $result = $reflector->reflect("Hello", $batchResults);

        $this->assertEquals('finish', $result['decision']);
        $this->assertStringContainsString('пуста', $result['thought']);
    }
}
