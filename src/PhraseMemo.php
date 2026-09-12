<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\AutoTranslator;

class PhraseMemo
{
    /**
     * @var array<string, PhraseResolution>
     */
    private array $resolutions = [];

    /**
     * @var array<int, array<string, mixed>>
     */
    private array $componentPaths = [];

    public function resolution(string $key): ?PhraseResolution
    {
        return $this->resolutions[$key] ?? null;
    }

    public function rememberResolution(string $key, PhraseResolution $resolution): PhraseResolution
    {
        $this->resolutions[$key] = $resolution;

        return $resolution;
    }

    /**
     * @param  array<string, mixed>  $path
     */
    public function rememberComponentPath(int $objectId, array $path): void
    {
        $this->componentPaths[$objectId] = $path;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function componentPath(int $objectId): ?array
    {
        return $this->componentPaths[$objectId] ?? null;
    }

    public function flush(): void
    {
        $this->resolutions = [];
        $this->componentPaths = [];
    }
}
