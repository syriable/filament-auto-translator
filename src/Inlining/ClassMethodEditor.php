<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\AutoTranslator\Inlining;

use Syriable\Filament\Plugins\AutoTranslator\Enums\Chrome;

/**
 * Adds a chrome method returning `__('key')` to the first class in a file.
 *
 * An existing method is left alone unless it returns the bare key as a
 * string, which is rewritten to `__()` — never a custom body.
 *
 * @internal
 */
final class ClassMethodEditor
{
    public function ensure(string $source, Chrome $chrome, string $key): string
    {
        $tokens = new PhpTokens($source);
        $class = $this->classBody($tokens);

        if ($class === null) {
            return $source;
        }

        $body = $this->methodBody($tokens, $class, $chrome->method());

        if ($body === null) {
            return PhpTokens::apply($source, [['offset' => $tokens->offset($class[1]), 'length' => 0, 'text' => $this->render($chrome, $key)]]);
        }

        $text = substr($source, $body[0], $body[1] - $body[0]);
        $quoted = preg_quote($key, '/');
        $wrapped = preg_replace('/return\s+[\'"]'.$quoted.'[\'"]\s*;/', "return __('".addcslashes($key, "'\\")."');", $text, 1);

        if (! is_string($wrapped) || $wrapped === $text) {
            return $source;
        }

        return PhpTokens::apply($source, [['offset' => $body[0], 'length' => $body[1] - $body[0], 'text' => $wrapped]]);
    }

    /**
     * @return array{0: int, 1: int}|null token indexes of the class's braces
     */
    private function classBody(PhpTokens $tokens): ?array
    {
        for ($index = 0, $count = $tokens->count(); $index < $count; $index++) {
            if ($tokens->id($index) !== T_CLASS) {
                continue;
            }

            $open = $tokens->next($index, '{');
            $close = $open === null ? null : $tokens->closing($open);

            return $open !== null && $close !== null ? [$open, $close] : null;
        }

        return null;
    }

    /**
     * @param  array{0: int, 1: int}  $class
     * @return array{0: int, 1: int}|null byte range of the method's body, braces included
     */
    private function methodBody(PhpTokens $tokens, array $class, string $method): ?array
    {
        for ($index = $class[0] + 1; $index < $class[1]; $index++) {
            // skip nested bodies: only the class's own methods count
            if ($tokens->text($index) === '{') {
                $index = $tokens->closing($index) ?? $class[1];

                continue;
            }

            $name = $tokens->id($index) === T_FUNCTION ? $tokens->nextSignificant($index + 1) : null;

            if ($name === null || ! $tokens->is($name, T_STRING, $method)) {
                continue;
            }

            $open = $tokens->next($name, '{');
            $close = $open === null ? null : $tokens->closing($open);

            return $open !== null && $close !== null ? [$tokens->offset($open), $tokens->end($close)] : null;
        }

        return null;
    }

    private function render(Chrome $chrome, string $key): string
    {
        $static = $chrome->isStatic() ? 'static ' : '';
        $key = addcslashes($key, "'\\");

        return <<<PHP

    public {$static}function {$chrome->method()}(): {$chrome->returnType()}
    {
        return __('{$key}');
    }

PHP;
    }
}
