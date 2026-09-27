<?php

declare(strict_types=1);

namespace Syriable\FilamentAutoTranslator\Inlining;

/**
 * Adds setter calls to `::make('name')` chains in PHP source.
 *
 * A setter already on the chain is left alone, except one passed the bare
 * key as a string, which is wrapped in `__()`. The inserted calls follow the
 * chain's existing layout: on new lines when it is multiline.
 *
 * @internal
 */
final class ChainEditor
{
    /**
     * @param  list<array{method: string, key: string}>  $calls
     */
    public function onMake(string $source, string $name, array $calls): string
    {
        if ($calls === [] || $name === '') {
            return $source;
        }

        $tokens = new PhpTokens($source);
        $edits = [];

        foreach ($this->makeChains($tokens, $name) as $chain) {
            $edits = [...$edits, ...$this->edits($chain, $calls)];
        }

        return PhpTokens::apply($source, $edits);
    }

    /**
     * Adds setters to each `Notification::make()` with the given status inside
     * the `make('action')` chain of an action.
     *
     * @param  list<array{method: string, key: string}>  $calls
     */
    public function onNotification(string $source, string $action, string $status, array $calls): string
    {
        if ($calls === [] || $action === '' || $status === '') {
            return $source;
        }

        $tokens = new PhpTokens($source);
        $edits = [];

        foreach ($this->makeChains($tokens, $action) as $chain) {
            if (! str_ends_with($chain['class'], 'Action')) {
                continue;
            }

            foreach ($this->notificationChains($tokens, $chain['from'], $chain['until']) as $notification) {
                if ($this->method($notification['methods'], $status) !== null) {
                    $edits = [...$edits, ...$this->edits($notification, $calls)];
                }
            }
        }

        return PhpTokens::apply($source, $edits);
    }

    /**
     * @return list<array{insertAt: int, gap: string, methods: list<array{name: string, start: int, end: int, key: ?string, wrapped: bool}>, class: string, from: int, until: int}>
     */
    private function makeChains(PhpTokens $tokens, string $name): array
    {
        $chains = [];

        for ($index = 0, $count = $tokens->count(); $index < $count; $index++) {
            $open = $this->makeCallOpen($tokens, $index);
            $argument = $open === null ? null : $tokens->nextSignificant($open + 1);

            if ($argument === null || $tokens->stringLiteral($argument) !== $name) {
                continue;
            }

            if (($close = $tokens->closing($open)) !== null) {
                $chains[] = $this->chain($tokens, $index, $close);
            }
        }

        return $chains;
    }

    /**
     * @return list<array{insertAt: int, gap: string, methods: list<array{name: string, start: int, end: int, key: ?string, wrapped: bool}>, class: string, from: int, until: int}>
     */
    private function notificationChains(PhpTokens $tokens, int $from, int $until): array
    {
        $chains = [];

        for ($index = $from; $index < $until; $index++) {
            $open = $this->makeCallOpen($tokens, $index);
            $close = $open === null ? null : $tokens->nextSignificant($open + 1);

            if ($close !== null && $tokens->text($close) === ')' && $tokens->classBefore($index) === 'Notification') {
                $chains[] = $this->chain($tokens, $index, $close);
            }
        }

        return $chains;
    }

    /**
     * The `(` of a `::make(` call whose `::` is at `$index`.
     */
    private function makeCallOpen(PhpTokens $tokens, int $index): ?int
    {
        if ($tokens->id($index) !== T_DOUBLE_COLON) {
            return null;
        }

        $make = $tokens->nextSignificant($index + 1);

        if ($make === null || ! $tokens->is($make, T_STRING, 'make')) {
            return null;
        }

        $open = $tokens->nextSignificant($make + 1);

        return $open !== null && $tokens->text($open) === '(' ? $open : null;
    }

    /**
     * The method calls chained after a `make()` call, up to the end of the
     * expression.
     *
     * @return array{insertAt: int, gap: string, methods: list<array{name: string, start: int, end: int, key: ?string, wrapped: bool}>, class: string, from: int, until: int}
     */
    private function chain(PhpTokens $tokens, int $doubleColon, int $close): array
    {
        $count = $tokens->count();
        $cursor = $close + 1;
        $gap = '';

        while ($cursor < $count && $tokens->isIgnorable($cursor)) {
            $gap .= $tokens->text($cursor++);
        }

        $methods = [];

        for ($depth = 0; $cursor < $count; $cursor++) {
            $text = $tokens->text($cursor);

            if ($depth === 0 && ($text === ',' || $text === ';')) {
                break;
            }

            if (in_array($text, ['(', '[', '{'], true)) {
                $depth++;
            } elseif (in_array($text, [')', ']', '}'], true)) {
                if ($depth === 0) {
                    break;
                }

                $depth--;
            }

            if ($depth === 0 && $tokens->id($cursor) === T_OBJECT_OPERATOR && ($method = $this->methodCall($tokens, $cursor)) !== null) {
                $methods[] = $method;
            }
        }

        return [
            'insertAt' => $tokens->end($close),
            'gap' => $gap,
            'methods' => $methods,
            'class' => $tokens->classBefore($doubleColon),
            'from' => $close + 1,
            'until' => $cursor,
        ];
    }

    /**
     * @return array{name: string, start: int, end: int, key: ?string, wrapped: bool}|null
     */
    private function methodCall(PhpTokens $tokens, int $arrow): ?array
    {
        $name = $tokens->nextSignificant($arrow + 1);
        $open = $name === null ? null : $tokens->nextSignificant($name + 1);

        if ($name === null || $tokens->id($name) !== T_STRING || $open === null || $tokens->text($open) !== '(') {
            return null;
        }

        $close = $tokens->closing($open);

        if ($close === null) {
            return null;
        }

        [$key, $wrapped] = $this->keyArgument($tokens, $open, $close);

        return [
            'name' => $tokens->text($name),
            'start' => $tokens->offset($arrow),
            'end' => $tokens->end($close),
            'key' => $key,
            'wrapped' => $wrapped,
        ];
    }

    /**
     * The key when the only argument is `'key'` or `__('key')`.
     *
     * @return array{0: ?string, 1: bool}
     */
    private function keyArgument(PhpTokens $tokens, int $open, int $close): array
    {
        $first = $tokens->nextSignificant($open + 1);

        if ($first === null || $first >= $close) {
            return [null, false];
        }

        if (($literal = $tokens->stringLiteral($first)) !== null) {
            return $tokens->nextSignificant($first + 1) === $close ? [$literal, false] : [null, false];
        }

        $innerOpen = $tokens->is($first, T_STRING, '__') ? $tokens->nextSignificant($first + 1) : null;
        $string = $innerOpen !== null && $tokens->text($innerOpen) === '(' ? $tokens->nextSignificant($innerOpen + 1) : null;
        $literal = $string === null ? null : $tokens->stringLiteral($string);
        $innerClose = $literal === null ? null : $tokens->nextSignificant($string + 1);

        if ($innerClose === null || $tokens->text($innerClose) !== ')' || $tokens->nextSignificant($innerClose + 1) !== $close) {
            return [null, false];
        }

        return [$literal, true];
    }

    /**
     * @param  array{insertAt: int, gap: string, methods: list<array{name: string, start: int, end: int, key: ?string, wrapped: bool}>}  $chain
     * @param  list<array{method: string, key: string}>  $calls
     * @return list<array{offset: int, length: int, text: string}>
     */
    private function edits(array $chain, array $calls): array
    {
        $edits = [];
        $missing = [];

        foreach ($calls as $call) {
            $existing = $this->method($chain['methods'], $call['method']);

            if ($existing === null) {
                $missing[] = $this->render($call);
            } elseif ($existing['key'] === $call['key'] && ! $existing['wrapped']) {
                $edits[] = ['offset' => $existing['start'], 'length' => $existing['end'] - $existing['start'], 'text' => $this->render($call)];
            }
        }

        if ($missing !== []) {
            $gap = $chain['gap'];
            $edits[] = ['offset' => $chain['insertAt'], 'length' => 0, 'text' => $gap.implode($gap, $missing)];
        }

        return $edits;
    }

    /**
     * @param  list<array{name: string, start: int, end: int, key: ?string, wrapped: bool}>  $methods
     * @return array{name: string, start: int, end: int, key: ?string, wrapped: bool}|null
     */
    private function method(array $methods, string $name): ?array
    {
        foreach ($methods as $method) {
            if ($method['name'] === $name) {
                return $method;
            }
        }

        return null;
    }

    /**
     * @param  array{method: string, key: string}  $call
     */
    private function render(array $call): string
    {
        return "->{$call['method']}(__('".addcslashes($call['key'], "'\\")."'))";
    }
}
