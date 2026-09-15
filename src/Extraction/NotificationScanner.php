<?php

declare(strict_types=1);

namespace Syriable\MessageCatalog\Extraction;

use Closure;
use Filament\Actions\Action;
use ReflectionFunction;
use Throwable;

class NotificationScanner
{
    /**
     * @return array<int, string>
     */
    public function statuses(Action $action): array
    {
        return $this->statusesIn((string) $this->closureSource($action->getActionFunction()));
    }

    /**
     * @return array<int, string>
     */
    public function statusesIn(string $source): array
    {
        if ($source === '' || ! str_contains($source, 'Notification::make')) {
            return [];
        }

        $found = [];

        foreach (['success', 'danger', 'info', 'warning'] as $status) {
            if (preg_match('/->\s*'.$status.'\s*\(/', $source) === 1) {
                $found[] = $status;
            }
        }

        return $found;
    }

    private function closureSource(?Closure $closure): ?string
    {
        if (! $closure instanceof Closure) {
            return null;
        }

        try {
            $reflection = new ReflectionFunction($closure);
        } catch (Throwable) {
            return null;
        }

        $file = $reflection->getFileName();
        $start = $reflection->getStartLine();
        $end = $reflection->getEndLine();

        if (! is_string($file) || $start < 1 || $end < $start || ! is_file($file)) {
            return null;
        }

        $lines = file($file);

        if ($lines === false) {
            return null;
        }

        return implode('', array_slice($lines, $start - 1, $end - $start + 1));
    }
}
