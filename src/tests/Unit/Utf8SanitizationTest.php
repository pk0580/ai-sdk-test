<?php

namespace Tests\Unit;

use App\Ai\Utils\JsonSanitizer;
use PHPUnit\Framework\TestCase;

class Utf8SanitizationTest extends TestCase
{
    public function test_json_sanitizer_removes_invalid_utf8_sequences(): void
    {
        // Невалидная UTF-8 последовательность (незавершенный многобайтовый символ)
        $invalidUtf8 = "Hello " . chr(0x80) . " world";

        $sanitized = JsonSanitizer::sanitizeUtf8($invalidUtf8);

        // Проверяем, что json_encode не падает на очищенной строке
        $json = json_encode(['data' => $sanitized]);
        $this->assertNotFalse($json, "json_encode failed after JsonSanitizer::sanitizeUtf8: " . json_last_error_msg());
    }

    public function test_json_encode_fails_on_raw_invalid_utf8(): void
    {
        $invalidUtf8 = "Hello " . chr(0x80) . " world";

        $json = @json_encode(['data' => $invalidUtf8]);

        if ($json === false) {
            $this->assertEquals(JSON_ERROR_UTF8, json_last_error());
        } else {
            // В PHP 7.2+ json_encode может возвращать NULL при ошибке
            $this->assertNull($json, "json_encode should return null or false on invalid UTF-8");
        }
    }
}
