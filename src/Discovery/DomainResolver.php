<?php

declare(strict_types=1);

namespace Syriable\Translation\Discovery;

use ReflectionClass;
use Syriable\Translation\Attributes\TranslationDomain;
use Throwable;

/**
 * Answers which translation domain a class belongs to.
 *
 * Both discovery and runtime binding ask this, so a class declares its domain
 * once and in one way, wherever it is used from.
 */
class DomainResolver
{
    /** @var array<class-string, string|null> */
    private array $memo = [];

    public function for(object|string $subject): ?string
    {
        $class = is_object($subject) ? $subject::class : $subject;

        if (array_key_exists($class, $this->memo)) {
            return $this->memo[$class];
        }

        return $this->memo[$class] = $this->resolve($class);
    }

    public function flush(): void
    {
        $this->memo = [];
    }

    /**
     * @param  class-string  $class
     */
    private function resolve(string $class): ?string
    {
        if (! class_exists($class)) {
            return null;
        }

        $declared = $this->declaredOn($class);

        if ($declared !== null) {
            return $declared;
        }

        return $this->fromMethod($class);
    }

    /**
     * The domain a class declares with #[TranslationDomain], its own or an
     * inherited one. Null when it declares none.
     *
     * @param  class-string  $class
     */
    public function declaredOn(string $class): ?string
    {
        $reflection = new ReflectionClass($class);

        while ($reflection !== false) {
            $attributes = $reflection->getAttributes(TranslationDomain::class);

            if ($attributes !== []) {
                return $attributes[0]->newInstance()->domain;
            }

            $reflection = $reflection->getParentClass();
        }

        return null;
    }

    /**
     * Resources derive their domain from the prefix map rather than declaring it.
     *
     * @param  class-string  $class
     */
    private function fromMethod(string $class): ?string
    {
        if (! method_exists($class, 'translationDomain')) {
            return null;
        }

        try {
            $domain = $class::translationDomain();
        } catch (Throwable) {
            return null;
        }

        return is_string($domain) && $domain !== '' ? $domain : null;
    }
}
