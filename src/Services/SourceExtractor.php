<?php

namespace OneLearningCommunity\LaravelModelExplorer\Services;

use ReflectionMethod;

class SourceExtractor
{
    /**
     * Returns the dedented source (including any preceding PHPDoc block) and
     * location metadata for the given method, or null when the source is
     * unavailable (eval'd class, PHAR, unreadable file, etc.).
     *
     * @return array{code: string, file: string, start_line: int, doc_summary: ?string}|null
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

        $methodStart = $method->getStartLine(); // 1-based
        $docComment = $method->getDocComment();

        // Default: snippet starts at the method declaration line.
        $snippetStart = $methodStart;

        // If there is a docblock, scan backwards to find its opening /** line,
        // skipping over PHP 8 attributes (#[...]) on the way.
        if ($docComment !== false) {
            $idx = $methodStart - 2; // 0-based index of the line just before the method

            while ($idx >= 0 && preg_match('/^\s*(#\[|$)/', $lines[$idx])) {
                $idx--;
            }

            if ($idx >= 0 && str_contains($lines[$idx], '*/')) {
                while ($idx >= 0 && ! str_contains($lines[$idx], '/**')) {
                    $idx--;
                }

                if ($idx >= 0) {
                    $snippetStart = $idx + 1; // 1-based
                }
            }
        }

        $codeLines = array_slice(
            $lines,
            $snippetStart - 1,
            $method->getEndLine() - $snippetStart + 1,
        );

        return [
            'code' => self::dedent($codeLines),
            'file' => $file,
            'start_line' => $snippetStart,
            'doc_summary' => $docComment !== false ? self::extractSummary($docComment) : null,
        ];
    }

    /**
     * Extracts the summary line (first non-tag, non-empty line) from a PHPDoc comment,
     * or null when no summary is present.
     */
    public static function docSummary(ReflectionMethod $method): ?string
    {
        $doc = $method->getDocComment();

        return $doc !== false ? self::extractSummary($doc) : null;
    }

    /**
     * Returns the summary line of a PHPDoc comment: the first non-empty line that
     * does not begin with a @tag. Returns null when no summary is present.
     */
    private static function extractSummary(string $docComment): ?string
    {
        foreach (explode("\n", $docComment) as $line) {
            // Strip leading docblock markers (/**, *, */) and trailing */
            $stripped = preg_replace('/^\s*(\/\*{1,2}|\*\/|\*)\s*/', '', $line);
            $stripped = trim(preg_replace('/\s*\*\/\s*$/', '', $stripped));

            if ($stripped !== '' && ! str_starts_with($stripped, '@')) {
                return $stripped;
            }
        }

        return null;
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
