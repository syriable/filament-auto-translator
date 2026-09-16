<?php

declare(strict_types=1);

namespace Syriable\Translation\Apply;

class ComponentChainEditor
{
    /**
     * @param  array<int, array{method: string, key: string}>  $calls
     */
    public function insertAfterMake(string $source, string $machineName, array $calls): string
    {
        if ($calls === [] || $machineName === '') {
            return $source;
        }

        $tokens = token_get_all($source);
        $mutations = [];

        foreach ($this->makeCalls($tokens, $machineName) as $call) {
            $mutations = [...$mutations, ...$this->mutationsFor($call, $calls)];
        }

        return $this->applyMutations($source, $mutations);
    }

    /**
     * @param  array<int, array{method: string, key: string}>  $calls
     */
    public function insertOnNotificationMake(string $source, string $actionName, string $status, array $calls): string
    {
        if ($calls === [] || $actionName === '' || $status === '') {
            return $source;
        }

        $tokens = token_get_all($source);
        $mutations = [];

        foreach ($this->makeCalls($tokens, $actionName) as $actionCall) {
            if (! str_ends_with($actionCall['class'], 'Action')) {
                continue;
            }

            foreach ($this->notificationMakes($tokens, $actionCall['from'], $actionCall['until']) as $notification) {
                if ($this->existingMethod($notification['methods'], $status) === null) {
                    continue;
                }

                $mutations = [...$mutations, ...$this->mutationsFor($notification, $calls)];
            }
        }

        return $this->applyMutations($source, $mutations);
    }

    /**
     * @param  array<int, string|array{0: int, 1: string, 2: int}>  $tokens
     * @return array<int, array{insertAt: int, gap: string, methods: array<int, array{name: string, start: int, end: int, rawKey: ?string, wrapped: bool}>, class: string, from: int, until: int}>
     */
    private function makeCalls(array $tokens, string $machineName): array
    {
        $calls = [];
        $count = count($tokens);

        for ($index = 0; $index < $count; $index++) {
            if ($this->tokenId($tokens[$index]) === T_DOUBLE_COLON) {
                $makeIndex = $this->nextSignificant($tokens, $index + 1);

                if ($makeIndex !== null && $this->tokenIs($tokens[$makeIndex], T_STRING, 'make')) {
                    $openIndex = $this->nextSignificant($tokens, $makeIndex + 1);

                    if ($openIndex !== null && $this->tokenText($tokens[$openIndex]) === '(') {
                        $argumentIndex = $this->nextSignificant($tokens, $openIndex + 1);

                        if ($argumentIndex !== null && $this->stringLiteral($tokens[$argumentIndex]) === $machineName) {
                            $closeIndex = $this->matchingParen($tokens, $openIndex);

                            if ($closeIndex !== null) {
                                $calls[] = $this->makeSite($tokens, $index, $closeIndex);
                            }
                        }
                    }
                }
            }
        }

        return $calls;
    }

    /**
     * @param  array<int, string|array{0: int, 1: string, 2: int}>  $tokens
     * @return array<int, array{insertAt: int, gap: string, methods: array<int, array{name: string, start: int, end: int, rawKey: ?string, wrapped: bool}>, class: string, from: int, until: int}>
     */
    private function notificationMakes(array $tokens, int $from, int $until): array
    {
        $calls = [];

        for ($index = $from; $index < $until; $index++) {
            if ($this->tokenId($tokens[$index]) !== T_DOUBLE_COLON) {
                continue;
            }

            if ($this->shortClassName($tokens, $index) !== 'Notification') {
                continue;
            }

            $makeIndex = $this->nextSignificant($tokens, $index + 1);

            if ($makeIndex === null || ! $this->tokenIs($tokens[$makeIndex], T_STRING, 'make')) {
                continue;
            }

            $openIndex = $this->nextSignificant($tokens, $makeIndex + 1);

            if ($openIndex === null || $this->tokenText($tokens[$openIndex]) !== '(') {
                continue;
            }

            $argumentIndex = $this->nextSignificant($tokens, $openIndex + 1);

            if ($argumentIndex === null || $this->tokenText($tokens[$argumentIndex]) !== ')') {
                continue;
            }

            $calls[] = $this->makeSite($tokens, $index, $argumentIndex);
        }

        return $calls;
    }

    /**
     * @param  array<int, string|array{0: int, 1: string, 2: int}>  $tokens
     * @return array{insertAt: int, gap: string, methods: array<int, array{name: string, start: int, end: int, rawKey: ?string, wrapped: bool}>, class: string, from: int, until: int}
     */
    private function makeSite(array $tokens, int $doubleColonIndex, int $closeIndex): array
    {
        $chain = $this->chainAfter($tokens, $closeIndex + 1);

        return [
            'insertAt' => $this->offsetOf($tokens, $closeIndex) + strlen($this->tokenText($tokens[$closeIndex])),
            'gap' => $chain['gap'],
            'methods' => $chain['methods'],
            'class' => $this->shortClassName($tokens, $doubleColonIndex),
            'from' => $closeIndex + 1,
            'until' => $chain['end'],
        ];
    }

    /**
     * @param  array<int, string|array{0: int, 1: string, 2: int}>  $tokens
     * @return array{gap: string, methods: array<int, array{name: string, start: int, end: int, rawKey: ?string, wrapped: bool}>, end: int}
     */
    private function chainAfter(array $tokens, int $index): array
    {
        $count = count($tokens);
        $gap = '';
        $cursor = $index;
        $methods = [];

        while ($cursor < $count && $this->isIgnorable($tokens[$cursor])) {
            $gap .= $this->tokenText($tokens[$cursor]);
            $cursor++;
        }

        $depth = 0;

        while ($cursor < $count) {
            $id = $this->tokenId($tokens[$cursor]);
            $text = $this->tokenText($tokens[$cursor]);

            if ($depth === 0 && ($text === ',' || $text === ';')) {
                break;
            }

            if ($text === '(' || $text === '[' || $text === '{') {
                $depth++;
            }

            if ($text === ')' || $text === ']' || $text === '}') {
                if ($depth === 0) {
                    break;
                }

                $depth--;
            }

            if ($depth === 0 && $id === T_OBJECT_OPERATOR) {
                $method = $this->methodCall($tokens, $cursor);

                if ($method !== null) {
                    $methods[] = $method;
                }
            }

            $cursor++;
        }

        return [
            'gap' => $gap,
            'methods' => $methods,
            'end' => $cursor,
        ];
    }

    /**
     * @param  array{insertAt: int, gap: string, methods: array<int, array{name: string, start: int, end: int, rawKey: ?string, wrapped: bool}>}  $call
     * @param  array<int, array{method: string, key: string}>  $calls
     * @return array<int, array{offset: int, length: int, text: string}>
     */
    private function mutationsFor(array $call, array $calls): array
    {
        $mutations = [];
        $missing = [];

        foreach ($calls as $desired) {
            $existing = $this->existingMethod($call['methods'], $desired['method']);

            if ($existing === null) {
                $missing[] = $desired;

                continue;
            }

            if ($existing['rawKey'] === $desired['key'] && $existing['wrapped'] === false) {
                $mutations[] = [
                    'offset' => $existing['start'],
                    'length' => $existing['end'] - $existing['start'],
                    'text' => $this->renderCall($desired),
                ];
            }
        }

        if ($missing !== []) {
            $mutations[] = [
                'offset' => $call['insertAt'],
                'length' => 0,
                'text' => $this->renderCalls($missing, $call['gap']),
            ];
        }

        return $mutations;
    }

    /**
     * @param  array<int, array{offset: int, length: int, text: string}>  $mutations
     */
    private function applyMutations(string $source, array $mutations): string
    {
        usort(
            $mutations,
            fn (array $left, array $right): int => $right['offset'] <=> $left['offset'],
        );

        foreach ($mutations as $mutation) {
            $source = substr($source, 0, $mutation['offset'])
                .$mutation['text']
                .substr($source, $mutation['offset'] + $mutation['length']);
        }

        return $source;
    }

    /**
     * @param  array<int, array{name: string, start: int, end: int, rawKey: ?string, wrapped: bool}>  $methods
     * @return array{name: string, start: int, end: int, rawKey: ?string, wrapped: bool}|null
     */
    private function existingMethod(array $methods, string $name): ?array
    {
        foreach ($methods as $method) {
            if ($method['name'] === $name) {
                return $method;
            }
        }

        return null;
    }

    /**
     * @param  array<int, string|array{0: int, 1: string, 2: int}>  $tokens
     * @return array{name: string, start: int, end: int, rawKey: ?string, wrapped: bool}|null
     */
    private function methodCall(array $tokens, int $arrowIndex): ?array
    {
        $methodIndex = $this->nextSignificant($tokens, $arrowIndex + 1);

        if ($methodIndex === null || $this->tokenId($tokens[$methodIndex]) !== T_STRING) {
            return null;
        }

        $openIndex = $this->nextSignificant($tokens, $methodIndex + 1);

        if ($openIndex === null || $this->tokenText($tokens[$openIndex]) !== '(') {
            return null;
        }

        $closeIndex = $this->matchingParen($tokens, $openIndex);

        if ($closeIndex === null) {
            return null;
        }

        [$rawKey, $wrapped] = $this->simpleArgument($tokens, $openIndex, $closeIndex);

        return [
            'name' => $this->tokenText($tokens[$methodIndex]),
            'start' => $this->offsetOf($tokens, $arrowIndex),
            'end' => $this->offsetOf($tokens, $closeIndex) + strlen($this->tokenText($tokens[$closeIndex])),
            'rawKey' => $rawKey,
            'wrapped' => $wrapped,
        ];
    }

    /**
     * @param  array<int, string|array{0: int, 1: string, 2: int}>  $tokens
     * @return array{0: ?string, 1: bool}
     */
    private function simpleArgument(array $tokens, int $openIndex, int $closeIndex): array
    {
        $first = $this->nextSignificant($tokens, $openIndex + 1);

        if ($first === null || $first >= $closeIndex) {
            return [null, false];
        }

        $literal = $this->stringLiteral($tokens[$first]);

        if ($literal !== null) {
            $after = $this->nextSignificant($tokens, $first + 1);

            return $after === $closeIndex ? [$literal, false] : [null, false];
        }

        if (! $this->tokenIs($tokens[$first], T_STRING, '__')) {
            return [null, false];
        }

        $innerOpen = $this->nextSignificant($tokens, $first + 1);

        if ($innerOpen === null || $this->tokenText($tokens[$innerOpen]) !== '(') {
            return [null, false];
        }

        $stringIndex = $this->nextSignificant($tokens, $innerOpen + 1);

        if ($stringIndex === null) {
            return [null, false];
        }

        $innerLiteral = $this->stringLiteral($tokens[$stringIndex]);

        if ($innerLiteral === null) {
            return [null, false];
        }

        $innerClose = $this->nextSignificant($tokens, $stringIndex + 1);

        if ($innerClose === null || $this->tokenText($tokens[$innerClose]) !== ')') {
            return [null, false];
        }

        $after = $this->nextSignificant($tokens, $innerClose + 1);

        return $after === $closeIndex ? [$innerLiteral, true] : [null, false];
    }

    /**
     * @param  array<int, array{method: string, key: string}>  $calls
     */
    private function renderCalls(array $calls, string $gap): string
    {
        $parts = array_map(
            fn (array $call): string => $this->renderCall($call),
            $calls,
        );

        if ($gap === '') {
            return implode('', $parts);
        }

        return $gap.implode($gap, $parts);
    }

    /**
     * @param  array{method: string, key: string}  $call
     */
    private function renderCall(array $call): string
    {
        $key = str_replace("'", "\\'", $call['key']);

        return "->{$call['method']}(__('{$key}'))";
    }

    /**
     * @param  array<int, string|array{0: int, 1: string, 2: int}>  $tokens
     */
    private function matchingParen(array $tokens, int $openIndex): ?int
    {
        $count = count($tokens);
        $depth = 0;

        for ($index = $openIndex; $index < $count; $index++) {
            $text = $this->tokenText($tokens[$index]);

            if ($text === '(') {
                $depth++;
            }

            if ($text === ')') {
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
            if (! $this->isIgnorable($tokens[$cursor])) {
                return $cursor;
            }
        }

        return null;
    }

    /**
     * @param  array<int, string|array{0: int, 1: string, 2: int}>  $tokens
     */
    private function previousSignificant(array $tokens, int $index): ?int
    {
        for ($cursor = $index; $cursor >= 0; $cursor--) {
            if (! $this->isIgnorable($tokens[$cursor])) {
                return $cursor;
            }
        }

        return null;
    }

    /**
     * @param  array<int, string|array{0: int, 1: string, 2: int}>  $tokens
     */
    private function shortClassName(array $tokens, int $doubleColonIndex): string
    {
        $index = $this->previousSignificant($tokens, $doubleColonIndex - 1);

        if ($index === null) {
            return '';
        }

        $parts = explode('\\', $this->tokenText($tokens[$index]));

        return (string) array_pop($parts);
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
    private function isIgnorable(array|string $token): bool
    {
        $id = $this->tokenId($token);

        return $id === T_WHITESPACE || $id === T_COMMENT || $id === T_DOC_COMMENT;
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
    private function stringLiteral(array|string $token): ?string
    {
        if ($this->tokenId($token) !== T_CONSTANT_ENCAPSED_STRING) {
            return null;
        }

        $text = $this->tokenText($token);

        if (strlen($text) < 2) {
            return null;
        }

        return stripslashes(substr($text, 1, -1));
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
