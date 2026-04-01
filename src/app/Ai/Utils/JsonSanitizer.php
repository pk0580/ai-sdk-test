<?php

namespace App\Ai\Utils;

class JsonSanitizer
{
    /**
     * Исправляет неэкранированные управляющие символы (переносы строк, табы) внутри JSON строк.
     * Это необходимо, когда LLM возвращает JSON, в котором внутри строковых значений
     * присутствуют реальные символы переноса строки или табуляции, что делает JSON невалидным.
     */
    public static function escapeControlCharacters(string $json): string
    {
        return preg_replace_callback('/"(?:[^"\\\\]|\\\\.)*"/', function ($matches) {
            return str_replace(["\n", "\r", "\t"], ['\n', '\r', '\t'], $matches[0]);
        }, $json);
    }

    /**
     * Очищает строку от некорректных UTF-8 символов.
     */
    public static function sanitizeUtf8(string $text): string
    {
        return mb_convert_encoding($text, 'UTF-8', 'UTF-8');
    }
}
