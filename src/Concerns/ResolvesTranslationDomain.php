<?php

declare(strict_types=1);

namespace Syriable\MessageCatalog\Concerns;

use Syriable\MessageCatalog\Binding\MessageBinder;
use Syriable\MessageCatalog\Discovery\DomainPrefixResolver;
use Syriable\MessageCatalog\Discovery\DomainResolver;
use Syriable\MessageCatalog\Enums\MessageSlot;
use Syriable\MessageCatalog\Enums\MessageSurface;
use Syriable\MessageCatalog\Enums\ResolutionOutcome;
use Syriable\MessageCatalog\MessageIdentity;

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
    protected static function catalogPhrase(MessageSurface $scope, MessageSlot $slot, array $path = []): ?string
    {
        $resolution = app(MessageBinder::class)->resolveIdentity(new MessageIdentity(
            catalogId: static::translationDomain(),
            scope: $scope,
            path: $path,
            name: '',
            slot: $slot,
        ));

        if (in_array($resolution->decision, [ResolutionOutcome::Bound, ResolutionOutcome::UsedFallback], true)) {
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
