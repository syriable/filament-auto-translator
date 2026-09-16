<?php

declare(strict_types=1);

namespace Syriable\Translation\Concerns;

use Syriable\Translation\Binding\MessageBinder;
use Syriable\Translation\Discovery\DomainPrefixResolver;
use Syriable\Translation\Discovery\DomainResolver;
use Syriable\Translation\Enums\MessageSlot;
use Syriable\Translation\Enums\MessageSurface;
use Syriable\Translation\Enums\ResolutionOutcome;
use Syriable\Translation\MessageIdentity;

trait ResolvesTranslationDomain
{
    /**
     * A declared #[TranslationDomain] wins over the prefix map, so a class
     * names its own domain the same way everywhere.
     */
    public static function translationDomain(): string
    {
        return app(DomainResolver::class)->declaredOn(static::class)
            ?? app(DomainPrefixResolver::class)->idFor(static::class);
    }

    /**
     * @param  array<int, string>  $path
     */
    protected static function catalogMessage(MessageSurface $scope, MessageSlot $slot, array $path = []): ?string
    {
        $resolution = app(MessageBinder::class)->resolveIdentity(new MessageIdentity(
            catalogId: static::translationDomain(),
            scope: $scope,
            path: $path,
            name: '',
            slot: $slot,
        ));

        if (in_array($resolution->decision, [ResolutionOutcome::Bound, ResolutionOutcome::UsedFallbackLocale], true)) {
            return $resolution->text;
        }

        if ($resolution->decision === ResolutionOutcome::Missing && $resolution->text !== null) {
            return $resolution->text;
        }

        return null;
    }

    protected static function callParentChrome(string $method): mixed
    {
        $parent = get_parent_class(static::class);

        if ($parent === false || ! method_exists($parent, $method)) {
            return null;
        }

        return parent::$method();
    }
}
