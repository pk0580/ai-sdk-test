<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Ai\Core\Reflector;
use App\Ai\DTO\Step;
use Laravel\Ai\Responses\AgentResponse;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\Data\Usage;
use Mockery;
use Laravel\Ai\AnonymousAgent;

class ReflectorTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_reflector_returns_correct_decision()
    {
        $jsonResponse = json_encode([
            'decision' => 'finish',
            'thought' => 'The calculation is complete.',
            'next_suggestion' => null
        ]);

        $mockResponse = new AgentResponse(
            'inv_123',
            $jsonResponse,
            new Usage(),
            new Meta()
        );

        $mockAgent = Mockery::mock('overload:Laravel\Ai\AnonymousAgent');
        $mockAgent->shouldReceive('prompt')
            ->once()
            ->andReturn($mockResponse);

        $reflector = new Reflector();
        $step = new Step('calculator', ['expression' => '25 * 17'], 'Calc');
        $result = 425;

        $analysis = $reflector->reflect('What is 25 * 17?', $step, $result);

        $this->assertEquals('finish', $analysis['decision']);
        $this->assertEquals('The calculation is complete.', $analysis['thought']);
        $this->assertNull($analysis['next_suggestion']);
    }

    public function test_reflector_handles_invalid_json()
    {
        $mockResponse = new AgentResponse(
            'inv_123',
            'Invalid JSON',
            new Usage(),
            new Meta()
        );

        $mockAgent = Mockery::mock('overload:Laravel\Ai\AnonymousAgent');
        $mockAgent->shouldReceive('prompt')
            ->once()
            ->andReturn($mockResponse);

        $reflector = new Reflector();
        $step = new Step('test', [], 'test');

        $analysis = $reflector->reflect('test', $step, 'result');

        $this->assertEquals('finish', $analysis['decision']);
        $this->assertStringContainsString('Не удалось распарсить', $analysis['thought']);
    }
}
