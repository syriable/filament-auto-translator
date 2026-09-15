<?php

declare(strict_types=1);

namespace Syriable\MessageCatalog\Apply;

class ClassMethodEditor
{
    /**
     * @param  array{method: string, key: string, static: bool, return: string}  $method
     */
    public function ensureTranslationMethod(string $source, array $method): string
    {
        $tokens = token_get_all($source);
        $existing = $this->namedMethod($source, $tokens, $method['method']);

        if ($existing === null) {
            return $this->insertMethod($source, $tokens, $method);
        }

        if ($this->returnsWrappedKey($existing['body'], $method['key'])) {
            return $source;
        }

        if ($this->returnsRawKey($existing['body'], $method['key'])) {
            return $this->wrapRawReturn($source, $existing, $method['key']);
        }

        return $source;
    }

    /**
     * @param  array<int, string|array{0: int, 1: string, 2: int}>  $tokens
     * @return array{body: string, start: int, end: int}|null
     */
    private function namedMethod(string $source, array $tokens, string $name): ?array
    {
        $class = $this->classSpan($tokens);

        if ($class === null) {
            return null;
        }

        $depth = 0;

        for ($index = $class['open']; $index <= $class['close']; $index++) {
            $text = $this->tokenText($tokens[$index]);

            if ($text === '{') {
                $depth++;
            }

            if ($text === '}') {
                $depth--;
            }

            if ($depth !== 1 || $this->tokenId($tokens[$index]) !== T_FUNCTION) {
                continue;
            }

            $nameIndex = $this->nextSignificant($tokens, $index + 1);

            if ($nameIndex === null || ! $this->tokenIs($tokens[$nameIndex], T_STRING, $name)) {
                continue;
            }

            $openIndex = $this->nextBrace($tokens, $nameIndex);

            if ($openIndex === null) {
                return null;
            }

            $closeIndex = $this->matchingBrace($tokens, $openIndex);

            if ($closeIndex === null) {
                return null;
            }

            $start = $this->offsetOf($tokens, $openIndex);
            $end = $this->offsetOf($tokens, $closeIndex) + 1;

            return [
                'body' => substr($source, $start, $end - $start),
                'start' => $start,
                'end' => $end,
            ];
        }

        return null;
    }

    /**
     * @param  array<int, string|array{0: int, 1: string, 2: int}>  $tokens
     * @param  array{method: string, key: string, static: bool, return: string}  $method
     */
    private function insertMethod(string $source, array $tokens, array $method): string
    {
        $class = $this->classSpan($tokens);

        if ($class === null) {
            return $source;
        }

        $closeOffset = $this->offsetOf($tokens, $class['close']);
        $rendered = $this->renderMethod($method);

        return substr($source, 0, $closeOffset).$rendered.substr($source, $closeOffset);
    }

    /**
     * @param  array{body: string, start: int, end: int}  $existing
     */
    private function wrapRawReturn(string $source, array $existing, string $key): string
    {
        $quoted = preg_quote($key, '/');
        $updated = preg_replace(
            '/return\s+[\'"]'.$quoted.'[\'"]\s*;/',
            "return __('{$key}');",
            $existing['body'],
            1,
        );

        if (! is_string($updated) || $updated === $existing['body']) {
            return $source;
        }

        return substr($source, 0, $existing['start']).$updated.substr($source, $existing['end']);
    }

    /**
     * @param  array{method: string, key: string, static: bool, return: string}  $method
     */
    private function renderMethod(array $method): string
    {
        $static = $method['static'] ? 'static ' : '';
        $key = str_replace("'", "\\'", $method['key']);

        return <<<PHP

    public {$static}function {$method['method']}(): {$method['return']}
    {
        return __('{$key}');
    }

PHP;
    }

    private function returnsWrappedKey(string $body, string $key): bool
    {
        return str_contains($body, "__('{$key}')")
            || str_contains($body, '__("'.$key.'")');
    }

    private function returnsRawKey(string $body, string $key): bool
    {
        return (bool) preg_match('/return\s+[\'"]'.preg_quote($key, '/').'[\'"]\s*;/', $body);
    }

    /**
     * @param  array<int, string|array{0: int, 1: string, 2: int}>  $tokens
     * @return array{open: int, close: int}|null
     */
    private function classSpan(array $tokens): ?array
    {
        $count = count($tokens);

        for ($index = 0; $index < $count; $index++) {
            if ($this->tokenId($tokens[$index]) !== T_CLASS) {
                continue;
            }

            $openIndex = $this->nextBrace($tokens, $index);

            if ($openIndex === null) {
                return null;
            }

            $closeIndex = $this->matchingBrace($tokens, $openIndex);

            if ($closeIndex === null) {
                return null;
            }

            return [
                'open' => $openIndex,
                'close' => $closeIndex,
            ];
        }

        return null;
    }

    /**
     * @param  array<int, string|array{0: int, 1: string, 2: int}>  $tokens
     */
    private function nextBrace(array $tokens, int $index): ?int
    {
        $count = count($tokens);

        for ($cursor = $index; $cursor < $count; $cursor++) {
            if ($this->tokenText($tokens[$cursor]) === '{') {
                return $cursor;
            }
        }

        return null;
    }

    /**
     * @param  array<int, string|array{0: int, 1: string, 2: int}>  $tokens
     */
    private function matchingBrace(array $tokens, int $openIndex): ?int
    {
        $count = count($tokens);
        $depth = 0;

        for ($index = $openIndex; $index < $count; $index++) {
            $text = $this->tokenText($tokens[$index]);

            if ($text === '{') {
                $depth++;
            }

            if ($text === '}') {
                $depth--;

                if ($depth === 0) {
                    return $index;
                }
            }
        }

        return null;
    }

    /**
     * @param  array<int, string|array{0: int, 1: string, 2: int}>  $tokens
     */
    private function nextSignificant(array $tokens, int $index): ?int
    {
        $count = count($tokens);

        for ($cursor = $index; $cursor < $count; $cursor++) {
            $id = $this->tokenId($tokens[$cursor]);

            if ($id !== T_WHITESPACE && $id !== T_COMMENT && $id !== T_DOC_COMMENT) {
                return $cursor;
            }
        }

        return null;
    }

    /**
     * @param  array<int, string|array{0: int, 1: string, 2: int}>  $tokens
     */
    private function offsetOf(array $tokens, int $index): int
    {
        $offset = 0;

        for ($cursor = 0; $cursor < $index; $cursor++) {
            $offset += strlen($this->tokenText($tokens[$cursor]));
        }

        return $offset;
    }

    /**
     * @param  string|array{0: int, 1: string, 2: int}  $token
     */
    private function tokenIs(array|string $token, int $id, string $text): bool
    {
        return $this->tokenId($token) === $id && $this->tokenText($token) === $text;
    }

    /**
     * @param  string|array{0: int, 1: string, 2: int}  $token
     */
    private function tokenText(array|string $token): string
    {
        return is_array($token) ? $token[1] : $token;
    }

    /**
     * @param  string|array{0: int, 1: string, 2: int}  $token
     */
    private function tokenId(array|string $token): int|string
    {
        return is_array($token) ? $token[0] : $token;
    }
}
