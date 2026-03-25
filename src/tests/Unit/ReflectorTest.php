<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Ai\Core\Reflector;
use App\Ai\DTO\Step;
use Mockery;
use App\Ai\Agents\CheapAnonymousAgent;

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

        CheapAnonymousAgent::fake([
            $jsonResponse
        ]);

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
        CheapAnonymousAgent::fake([
            'Invalid JSON'
        ]);

        $reflector = new Reflector();
        $step = new Step('test', [], 'test');

        $analysis = $reflector->reflect('test', $step, 'result');

        $this->assertEquals('finish', $analysis['decision']);
        $this->assertStringContainsString('Не удалось распарсить', $analysis['thought']);
    }
}
