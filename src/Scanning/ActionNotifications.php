<?php

declare(strict_types=1);

namespace Syriable\FilamentAutoTranslator\Scanning;

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use ReflectionFunction;
use Syriable\FilamentAutoTranslator\Binding\MessageOptions;
use Throwable;

/**
 * The notifications an action sends, read from its closure's source.
 *
 * A notification only exists while its action runs, so a console walk cannot
 * observe one. The action's source is read instead, looking for
 * `Notification::make()` and a status call.
 */
final class ActionNotifications
{
    private const array STATUSES = ['success', 'danger', 'info', 'warning'];

    /**
     * @var array<string, list<string>>
     */
    private array $sources = [];

    public function __construct(
        private readonly MessageOptions $options,
    ) {}

    /**
     * One notification per status the action sends, owned by the action so it
     * resolves under the action's path.
     *
     * @return list<Notification>
     */
    public function of(Action $action): array
    {
        $notifications = [];

        foreach ($this->statuses($action) as $status) {
            $notification = Notification::make()->status($status);
            $this->options->setOwner($notification, $action);
            $notifications[] = $notification;
        }

        return $notifications;
    }

    /**
     * @return list<string>
     */
    public function statuses(Action $action): array
    {
        $source = $this->sourceOf($action);

        if ($source === '' || ! str_contains($source, 'Notification::make')) {
            return [];
        }

        return array_values(array_filter(
            self::STATUSES,
            fn (string $status): bool => preg_match('/->\s*'.$status.'\s*\(/', $source) === 1,
        ));
    }

    private function sourceOf(Action $action): string
    {
        $closure = $action->getActionFunction();

        if ($closure === null) {
            return '';
        }

        try {
            $reflection = new ReflectionFunction($closure);
        } catch (Throwable) {
            return '';
        }

        $file = $reflection->getFileName();
        $start = $reflection->getStartLine();
        $end = $reflection->getEndLine();

        if ($file === false || $start === false || $end === false) {
            return '';
        }

        // an action file holds many actions; read it once per walk
        $lines = $this->sources[$file] ??= is_file($file) ? (file($file) ?: []) : [];

        return implode('', array_slice($lines, $start - 1, $end - $start + 1));
    }
}
