<?php

declare(strict_types=1);

namespace App\Infrastructure\Ai\Util;

final class JsonSanitizer
{
    /**
     * Escape unescaped control characters (newlines, tabs) inside JSON strings,
     * which LLMs frequently emit and which makes the JSON syntactically invalid.
     */
    public static function escapeControlCharacters(string $json): string
    {
        return preg_replace_callback(
            '/"(?:[^"\\\\]|\\\\.)*"/',
            static fn (array $m) => str_replace(["\n", "\r", "\t"], ['\n', '\r', '\t'], $m[0]),
            $json,
        ) ?? $json;
    }

    public static function sanitizeUtf8(string $text): string
    {
        return mb_convert_encoding($text, 'UTF-8', 'UTF-8');
    }
}
