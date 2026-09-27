<?php

declare(strict_types=1);

namespace Syriable\FilamentAutoTranslator\Inlining;

/**
 * A tokenized PHP source with the byte offset of every token, for editing
 * source without disturbing its formatting.
 *
 * @internal
 */
final class PhpTokens
{
    private const array PAIRS = ['(' => ')', '[' => ']', '{' => '}'];

    /**
     * @var list<string|array{0: int, 1: string, 2: int}>
     */
    private readonly array $tokens;

    /**
     * @var list<int>
     */
    private readonly array $offsets;

    public function __construct(string $source)
    {
        $this->tokens = token_get_all($source);

        $offsets = [];
        $offset = 0;

        foreach ($this->tokens as $index => $token) {
            $offsets[] = $offset;
            $offset += strlen($this->text($index));
        }

        $this->offsets = $offsets;
    }

    /**
     * Applies replacements given as byte ranges of the original source.
     *
     * @param  list<array{offset: int, length: int, text: string}>  $edits
     */
    public static function apply(string $source, array $edits): string
    {
        usort($edits, fn (array $a, array $b): int => $b['offset'] <=> $a['offset']);

        foreach ($edits as $edit) {
            $source = substr_replace($source, $edit['text'], $edit['offset'], $edit['length']);
        }

        return $source;
    }

    public function count(): int
    {
        return count($this->tokens);
    }

    public function text(int $index): string
    {
        $token = $this->tokens[$index];

        return is_array($token) ? $token[1] : $token;
    }

    public function id(int $index): int|string
    {
        $token = $this->tokens[$index];

        return is_array($token) ? $token[0] : $token;
    }

    public function is(int $index, int $id, string $text): bool
    {
        return $this->id($index) === $id && $this->text($index) === $text;
    }

    public function offset(int $index): int
    {
        return $this->offsets[$index];
    }

    /**
     * The byte offset just after the token.
     */
    public function end(int $index): int
    {
        return $this->offsets[$index] + strlen($this->text($index));
    }

    public function isIgnorable(int $index): bool
    {
        return in_array($this->id($index), [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true);
    }

    public function nextSignificant(int $from): ?int
    {
        for ($index = $from, $count = $this->count(); $index < $count; $index++) {
            if (! $this->isIgnorable($index)) {
                return $index;
            }
        }

        return null;
    }

    public function previousSignificant(int $from): ?int
    {
        for ($index = $from; $index >= 0; $index--) {
            if (! $this->isIgnorable($index)) {
                return $index;
            }
        }

        return null;
    }

    public function next(int $from, string $text): ?int
    {
        for ($index = $from, $count = $this->count(); $index < $count; $index++) {
            if ($this->text($index) === $text) {
                return $index;
            }
        }

        return null;
    }

    /**
     * The index of the bracket closing the one at `$open`.
     */
    public function closing(int $open): ?int
    {
        $opening = $this->text($open);
        $closing = self::PAIRS[$opening] ?? null;

        if ($closing === null) {
            return null;
        }

        for ($index = $open, $depth = 0, $count = $this->count(); $index < $count; $index++) {
            $text = $this->text($index);

            if ($text === $opening) {
                $depth++;
            } elseif ($text === $closing && --$depth === 0) {
                return $index;
            }
        }

        return null;
    }

    /**
     * The value of a quoted string literal token, or null.
     */
    public function stringLiteral(int $index): ?string
    {
        $text = $this->text($index);

        if ($this->id($index) !== T_CONSTANT_ENCAPSED_STRING || strlen($text) < 2) {
            return null;
        }

        return stripslashes(substr($text, 1, -1));
    }

    /**
     * The short class name before a `::` token.
     */
    public function classBefore(int $doubleColon): string
    {
        $index = $this->previousSignificant($doubleColon - 1);

        if ($index === null) {
            return '';
        }

        $parts = explode('\\', $this->text($index));

        return (string) end($parts);
    }
}
