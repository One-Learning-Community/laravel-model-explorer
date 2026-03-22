<?php

namespace OneLearningCommunity\LaravelModelExplorer\Services;

use ReflectionMethod;

class SourceExtractor
{
    /**
     * Returns the dedented source and location metadata for the given method, or null
     * when the source is unavailable (eval'd class, PHAR, unreadable file, etc.).
     *
     * @return array{code: string, file: string, start_line: int}|null
     */
    public static function forMethod(ReflectionMethod $method): ?array
    {
        $file = $method->getFileName();

        if ($file === false) {
            return null;
        }

        $lines = file($file, FILE_IGNORE_NEW_LINES);

        if ($lines === false) {
            return null;
        }

        $startLine = $method->getStartLine();

        $body = array_slice(
            $lines,
            $startLine - 1,
            $method->getEndLine() - $startLine + 1,
        );

        return [
            'code' => self::dedent($body),
            'file' => $file,
            'start_line' => $startLine,
        ];
    }

    /**
     * Strips the common leading whitespace from a block of lines so the snippet
     * renders flush-left regardless of how deeply the method was indented.
     *
     * @param  list<string>  $lines
     */
    private static function dedent(array $lines): string
    {
        $minIndent = PHP_INT_MAX;

        foreach ($lines as $line) {
            if (trim($line) === '') {
                continue;
            }

            preg_match('/^(\s*)/', $line, $m);
            $minIndent = min($minIndent, strlen($m[1]));
        }

        if ($minIndent === PHP_INT_MAX) {
            $minIndent = 0;
        }

        $dedented = array_map(
            fn (string $line) => substr($line, min($minIndent, strlen($line))),
            $lines,
        );

        return implode("\n", $dedented);
    }
}
