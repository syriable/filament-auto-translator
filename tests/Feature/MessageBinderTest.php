<?php

declare(strict_types=1);

use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\EmptyState;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Lang;
use Syriable\Translation\Binding\MessageBinder;
use Syriable\Translation\Binding\ResolutionExplainer;
use Syriable\Translation\Enums\MessageSlot;
use Syriable\Translation\Enums\ResolutionOutcome;
use Syriable\Translation\Tests\Fixtures\DomainForm;
use Syriable\Translation\Tests\Fixtures\DomainTable;
use Syriable\Translation\Tests\Fixtures\EditUser;
use Syriable\Translation\Tests\Fixtures\Schemas\User\ChromeForm;

beforeEach(function () {
    config()->set('translations.on_missing', 'debug');
    config()->set('translations.default_domain_prefix', 'filament');
    config()->set('translations.domain_prefixes', []);
    app(MessageBinder::class)->registerHooks();
});

it('fills an unset field label from the message catalog', function () {
    Lang::addLines([
        'filament/domain-form.form.components.email.label' => 'Email address',
    ], 'en');

    $livewire = app(DomainForm::class);
    $schema = Schema::make($livewire)->components([
        TextInput::make('email'),
    ]);

    $field = textInput($schema);

    expect($field->getLabel())->toBe('Email address');
});

it('does not overwrite a custom field label', function () {
    Lang::addLines([
        'filament/domain-form.form.components.email.label' => 'Email address',
    ], 'en');

    $livewire = app(DomainForm::class);
    $schema = Schema::make($livewire)->components([
        TextInput::make('email')->label('Work email'),
    ]);

    expect(textInput($schema)->getLabel())->toBe('Work email');
});

it('fills a field placeholder from the message catalog', function () {
    Lang::addLines([
        'filament/domain-form.form.components.email.placeholder' => 'name@example.com',
    ], 'en');

    $livewire = app(DomainForm::class);
    $schema = Schema::make($livewire)->components([
        TextInput::make('email'),
    ]);

    expect(textInput($schema)->getPlaceholder())->toBe('name@example.com');
});

it('keeps an explicit field placeholder', function () {
    Lang::addLines([
        'filament/domain-form.form.components.email.placeholder' => 'name@example.com',
    ], 'en');

    $livewire = app(DomainForm::class);
    $schema = Schema::make($livewire)->components([
        TextInput::make('email')->placeholder('you@company.com'),
    ]);

    expect(textInput($schema)->getPlaceholder())->toBe('you@company.com');
});

it('does not invent a field placeholder when the catalog omits it', function () {
    Lang::addLines([
        'filament/domain-form.form.components.email.label' => 'Email address',
    ], 'en');

    $livewire = app(DomainForm::class);
    $schema = Schema::make($livewire)->components([
        TextInput::make('email'),
    ]);

    expect(textInput($schema)->getPlaceholder())->toBeNull();
});

it('fills field before and after content from the message catalog', function () {
    Lang::addLines([
        'filament/domain-form.form.components.user.schema.email.before_content' => 'Before the email',
        'filament/domain-form.form.components.user.schema.email.after_content' => 'After the email',
    ], 'en');

    $livewire = app(DomainForm::class);
    $schema = Schema::make($livewire)->components([
        Section::make()
            ->key('user')
            ->schema([
                TextInput::make('email'),
            ]),
    ]);

    $field = sectionTextInput($schema);

    expect(chromeText($field, TextInput::BEFORE_CONTENT_SCHEMA_KEY)->getContent())->toBe('Before the email');
    expect(chromeText($field, TextInput::AFTER_CONTENT_SCHEMA_KEY)->getContent())->toBe('After the email');
});

it('does not treat visible before content as a catalog identifier', function () {
    $livewire = app(DomainForm::class);
    $schema = Schema::make($livewire)->components([
        TextInput::make('email')
            ->beforeContent('Shown in English'),
    ]);

    expect(chromeText(textInput($schema), TextInput::BEFORE_CONTENT_SCHEMA_KEY)->getContent())->toBe('Shown in English');
});

it('does not invent field before content when the catalog omits it', function () {
    $livewire = app(DomainForm::class);
    $schema = Schema::make($livewire)->components([
        TextInput::make('email'),
    ]);

    expect(textInput($schema)->getChildSchema(TextInput::BEFORE_CONTENT_SCHEMA_KEY))->toBeNull();
});

it('fills an infolist entry label from the message catalog', function () {
    Lang::addLines([
        'filament/domain-form.infolist.components.info.label' => 'Account info',
        'filament/domain-form.infolist.components.info.placeholder' => 'No info yet',
    ], 'en');

    $livewire = app(DomainForm::class);
    $schema = Schema::make($livewire)->components([
        TextEntry::make('info'),
    ]);

    $entry = $schema->getComponents()[0];

    expect($entry)->toBeInstanceOf(TextEntry::class)
        ->and($entry->getLabel())->toBe('Account info')
        ->and($entry->getPlaceholder())->toBe('No info yet');
});

it('keeps an explicit infolist entry label', function () {
    Lang::addLines([
        'filament/domain-form.infolist.components.info.label' => 'Account info',
    ], 'en');

    $livewire = app(DomainForm::class);
    $schema = Schema::make($livewire)->components([
        TextEntry::make('info')->label('Details'),
    ]);

    $entry = $schema->getComponents()[0];

    expect($entry)->toBeInstanceOf(TextEntry::class)
        ->and($entry->getLabel())->toBe('Details');
});

it('fills schema text content from the message catalog', function () {
    Lang::addLines([
        'filament/domain-form.form.components.info.body' => 'Account info',
        'filament/domain-form.form.components.info.tooltip' => 'More about this account',
    ], 'en');

    $livewire = app(DomainForm::class);
    $schema = Schema::make($livewire)->components([
        Text::make('info'),
    ]);

    $text = $schema->getComponents()[0];

    expect($text)->toBeInstanceOf(Text::class)
        ->and($text->getContent())->toBe('Account info')
        ->and($text->getTooltip())->toBe('More about this account');
});

it('keeps explicit schema text content', function () {
    Lang::addLines([
        'filament/domain-form.form.components.info.body' => 'Account info',
    ], 'en');

    $livewire = app(DomainForm::class);
    $schema = Schema::make($livewire)->components([
        Text::make('info')->content('Visible copy'),
    ]);

    $text = $schema->getComponents()[0];

    expect($text)->toBeInstanceOf(Text::class)
        ->and($text->getContent())->toBe('Visible copy');
});

it('renders the compiled key when schema text body is missing in debug mode', function () {
    $livewire = app(DomainForm::class);
    $schema = Schema::make($livewire)->components([
        Text::make('info'),
    ]);

    $text = $schema->getComponents()[0];

    expect($text)->toBeInstanceOf(Text::class)
        ->and($text->getContent())->toBe('filament/domain-form.form.components.info.body');
});

it('does not treat visible schema text as a catalog identifier', function () {
    $livewire = app(DomainForm::class);
    $schema = Schema::make($livewire)->components([
        Text::make('Hello world'),
    ]);

    $text = $schema->getComponents()[0];

    expect($text)->toBeInstanceOf(Text::class)
        ->and($text->getContent())->toBe('Hello world');
});

it('fills a callout heading from the message catalog', function () {
    Lang::addLines([
        'filament/domain-form.form.components.alert.heading' => 'Check this',
        'filament/domain-form.form.components.alert.description' => 'Something needs your attention.',
    ], 'en');

    $livewire = app(DomainForm::class);
    $schema = Schema::make($livewire)->components([
        Callout::make('alert'),
    ]);

    $callout = $schema->getComponents()[0];

    expect($callout)->toBeInstanceOf(Callout::class)
        ->and($callout->getHeading())->toBe('Check this')
        ->and($callout->getDescription())->toBe('Something needs your attention.');
});

it('keeps an explicit callout heading', function () {
    Lang::addLines([
        'filament/domain-form.form.components.alert.heading' => 'Check this',
    ], 'en');

    $livewire = app(DomainForm::class);
    $schema = Schema::make($livewire)->components([
        Callout::make('alert')->heading('Pay attention'),
    ]);

    $callout = $schema->getComponents()[0];

    expect($callout)->toBeInstanceOf(Callout::class)
        ->and($callout->getHeading())->toBe('Pay attention');
});

it('does not treat a visible callout heading as a catalog identifier', function () {
    $livewire = app(DomainForm::class);
    $schema = Schema::make($livewire)->components([
        Callout::make('Watch out'),
    ]);

    $callout = $schema->getComponents()[0];

    expect($callout)->toBeInstanceOf(Callout::class)
        ->and($callout->getHeading())->toBe('Watch out');
});

it('does not invent a callout description when the catalog omits it', function () {
    Lang::addLines([
        'filament/domain-form.form.components.alert.heading' => 'Check this',
    ], 'en');

    $livewire = app(DomainForm::class);
    $schema = Schema::make($livewire)->components([
        Callout::make('alert'),
    ]);

    $callout = $schema->getComponents()[0];

    expect($callout)->toBeInstanceOf(Callout::class)
        ->and($callout->getHeading())->toBe('Check this')
        ->and($callout->getDescription())->toBeNull();
});

it('fills a schema action label from the message catalog', function () {
    Lang::addLines([
        'filament/domain-form.form.components.actions.action.label' => 'Do this',
    ], 'en');

    $livewire = app(DomainForm::class);
    $schema = Schema::make($livewire)->components([
        Action::make('action'),
    ]);

    $action = $schema->getComponents()[0];

    expect($action)->toBeInstanceOf(Action::class)
        ->and($action->getLabel())->toBe('Do this');
});

it('keeps an explicit schema action label', function () {
    Lang::addLines([
        'filament/domain-form.form.components.actions.action.label' => 'Do this',
    ], 'en');

    $livewire = app(DomainForm::class);
    $schema = Schema::make($livewire)->components([
        Action::make('action')->label('Run now'),
    ]);

    $action = $schema->getComponents()[0];

    expect($action)->toBeInstanceOf(Action::class)
        ->and($action->getLabel())->toBe('Run now');
});

it('renders the compiled key when a schema action label is missing in debug mode', function () {
    $livewire = app(DomainForm::class);
    $schema = Schema::make($livewire)->components([
        Action::make('action'),
    ]);

    $action = $schema->getComponents()[0];

    expect($action)->toBeInstanceOf(Action::class)
        ->and($action->getLabel())->toBe('filament/domain-form.form.components.actions.action.label');
});

it('fills schema action modal chrome from the message catalog', function () {
    Lang::addLines([
        'filament/domain-form.form.components.actions.action.label' => 'Do this',
        'filament/domain-form.form.components.actions.action.modal_heading' => 'Confirm this',
        'filament/domain-form.form.components.actions.action.modal_description' => 'This cannot be undone.',
        'filament/domain-form.form.components.actions.action.modal_cancel_action_label' => 'Never mind',
        'filament/domain-form.form.components.actions.action.modal_submit_action_label' => 'Save',
    ], 'en');

    $livewire = app(DomainForm::class);
    $schema = Schema::make($livewire)->components([
        Action::make('action'),
    ]);

    $action = $schema->getComponents()[0];

    expect($action)->toBeInstanceOf(Action::class)
        ->and($action->getModalHeading())->toBe('Confirm this')
        ->and($action->getModalDescription())->toBe('This cannot be undone.')
        ->and($action->getModalCancelActionLabel())->toBe('Never mind')
        ->and($action->getModalSubmitActionLabel())->toBe('Save');
});

it('keeps an explicit schema action modal heading', function () {
    Lang::addLines([
        'filament/domain-form.form.components.actions.action.modal_heading' => 'Confirm this',
    ], 'en');

    $livewire = app(DomainForm::class);
    $schema = Schema::make($livewire)->components([
        Action::make('action')->modalHeading('Visible heading'),
    ]);

    $action = $schema->getComponents()[0];

    expect($action)->toBeInstanceOf(Action::class)
        ->and($action->getModalHeading())->toBe('Visible heading');
});

it('does not invent schema action modal chrome when the catalog omits it', function () {
    Lang::addLines([
        'filament/domain-form.form.components.actions.action.label' => 'Do this',
    ], 'en');

    $livewire = app(DomainForm::class);
    $schema = Schema::make($livewire)->components([
        Action::make('action'),
    ]);

    $action = $schema->getComponents()[0];

    expect($action)->toBeInstanceOf(Action::class)
        ->and($action->getCustomModalHeading())->toBeNull()
        ->and($action->getModalHeading())->toBe('Do this')
        ->and($action->getModalDescription())->toBeNull()
        ->and($action->getModalCancelActionLabel())->toBe(__('filament-actions::modal.actions.cancel.label'))
        ->and($action->getModalSubmitActionLabel())->toBe(__('filament-actions::modal.actions.submit.label'));
});

it('fills a table column label from the message catalog', function () {
    Lang::addLines([
        'filament/domain-table.table.columns.name.label' => 'Name',
        'filament/domain-table.table.columns.email.label' => 'E-mail',
        'filament/domain-table.table.columns.email.prefix' => 'E-mail: ',
    ], 'en');

    $livewire = app(DomainTable::class);
    $table = Table::make($livewire)->columns([
        TextColumn::make('name'),
        TextColumn::make('email'),
    ]);

    $name = $table->getColumn('name');
    $email = $table->getColumn('email');

    expect($name)->toBeInstanceOf(TextColumn::class)
        ->and($name->getLabel())->toBe('Name')
        ->and($email)->toBeInstanceOf(TextColumn::class)
        ->and($email->getLabel())->toBe('E-mail')
        ->and($email->getPrefix())->toBe('E-mail: ');
});

it('keeps an explicit table column label and prefix', function () {
    Lang::addLines([
        'filament/domain-table.table.columns.email.label' => 'E-mail',
        'filament/domain-table.table.columns.email.prefix' => 'E-mail: ',
    ], 'en');

    $livewire = app(DomainTable::class);
    $table = Table::make($livewire)->columns([
        TextColumn::make('email')
            ->label('Work email')
            ->prefix('Work: '),
    ]);

    $column = $table->getColumn('email');

    expect($column)->toBeInstanceOf(TextColumn::class)
        ->and($column->getLabel())->toBe('Work email')
        ->and($column->getPrefix())->toBe('Work: ');
});

it('does not invent a table column prefix when the catalog omits it', function () {
    Lang::addLines([
        'filament/domain-table.table.columns.email.label' => 'E-mail',
    ], 'en');

    $livewire = app(DomainTable::class);
    $table = Table::make($livewire)->columns([
        TextColumn::make('email'),
    ]);

    $column = $table->getColumn('email');

    expect($column)->toBeInstanceOf(TextColumn::class)
        ->and($column->getLabel())->toBe('E-mail')
        ->and($column->getPrefix())->toBeNull();
});

it('renders the compiled key when a table column label is missing in debug mode', function () {
    $livewire = app(DomainTable::class);
    $table = Table::make($livewire)->columns([
        TextColumn::make('email'),
    ]);

    $column = $table->getColumn('email');

    expect($column)->toBeInstanceOf(TextColumn::class)
        ->and($column->getLabel())->toBe('filament/domain-table.table.columns.email.label');
});

it('fills a table filter label from the message catalog', function () {
    Lang::addLines([
        'filament/domain-table.table.filters.is_featured.label' => 'Is featured',
        'filament/domain-table.table.filters.is_featured.indicator' => 'Featured only',
    ], 'en');

    $livewire = app(DomainTable::class);
    $table = Table::make($livewire)->filters([
        Filter::make('is_featured'),
    ]);

    $filter = tableFilter($table);

    expect($filter->getLabel())->toBe('Is featured')
        ->and(ResolutionExplainer::explain($filter, MessageSlot::Indicator)->text)->toBe('Featured only');
});

it('keeps an explicit table filter label', function () {
    Lang::addLines([
        'filament/domain-table.table.filters.is_featured.label' => 'Is featured',
    ], 'en');

    $livewire = app(DomainTable::class);
    $table = Table::make($livewire)->filters([
        Filter::make('is_featured')->label('Shown in English'),
    ]);

    expect(tableFilter($table)->getLabel())->toBe('Shown in English');
});

it('renders the compiled key when a table filter label is missing in debug mode', function () {
    $livewire = app(DomainTable::class);
    $table = Table::make($livewire)->filters([
        Filter::make('is_featured'),
    ]);

    expect(tableFilter($table)->getLabel())->toBe('filament/domain-table.table.filters.is_featured.label');
});

it('does not invent a table filter indicator when the catalog omits it', function () {
    Lang::addLines([
        'filament/domain-table.table.filters.is_featured.label' => 'Is featured',
    ], 'en');

    $livewire = app(DomainTable::class);
    $table = Table::make($livewire)->filters([
        Filter::make('is_featured'),
    ]);

    $filter = tableFilter($table);

    expect($filter->getLabel())->toBe('Is featured')
        ->and(ResolutionExplainer::explain($filter, MessageSlot::Indicator)->text)->toBeNull();
});

it('fills table record and toolbar action labels from the message catalog', function () {
    Lang::addLines([
        'filament/domain-table.table.record_actions.view.label' => 'View user',
        'filament/domain-table.table.toolbar_actions.bulk_action.label' => 'Bulk action',
        'filament/domain-table.table.empty_state_actions.create.label' => 'Create user',
    ], 'en');

    $livewire = app(DomainTable::class);
    $table = Table::make($livewire)
        ->recordActions([
            Action::make('view'),
        ])
        ->toolbarActions([
            Action::make('bulk_action'),
        ])
        ->emptyStateActions([
            Action::make('create'),
        ]);

    expect(tableAction($table->getRecordActions())->getLabel())->toBe('View user');
    expect(tableAction($table->getToolbarActions())->getLabel())->toBe('Bulk action');
    expect(tableAction($table->getEmptyStateActions())->getLabel())->toBe('Create user');
});

it('keeps an explicit table record action label', function () {
    Lang::addLines([
        'filament/domain-table.table.record_actions.view.label' => 'View user',
    ], 'en');

    $livewire = app(DomainTable::class);
    $table = Table::make($livewire)->recordActions([
        Action::make('view')->label('Shown in English'),
    ]);

    expect(tableAction($table->getRecordActions())->getLabel())->toBe('Shown in English');
});

it('renders the compiled key when a table record action label is missing in debug mode', function () {
    $livewire = app(DomainTable::class);
    $table = Table::make($livewire)->recordActions([
        Action::make('view'),
    ]);

    expect(tableAction($table->getRecordActions())->getLabel())
        ->toBe('filament/domain-table.table.record_actions.view.label');
});

it('fills an action schema field label from the message catalog', function () {
    Lang::addLines([
        'filament/domain-form.form.components.actions.action.schema.components.name_action.label' => 'Action name',
    ], 'en');

    $livewire = app(DomainForm::class);
    $page = Schema::make($livewire)->components([
        Action::make('action')
            ->schema([
                TextInput::make('name_action'),
            ]),
    ]);

    $action = schemaAction($page);
    $livewire->withMountedAction($action);

    $modal = $action->getSchema(
        Schema::make($livewire)->key('mountedActionSchema0'),
    );

    expect($modal)->not->toBeNull();

    $field = textInput($modal);

    expect($field->getLabel())->toBe('Action name');
});

it('keeps an explicit action schema field label', function () {
    Lang::addLines([
        'filament/domain-form.form.components.actions.action.schema.components.name_action.label' => 'Action name',
    ], 'en');

    $livewire = app(DomainForm::class);
    $page = Schema::make($livewire)->components([
        Action::make('action')
            ->schema([
                TextInput::make('name_action')->label('Visible name'),
            ]),
    ]);

    $action = schemaAction($page);
    $livewire->withMountedAction($action);

    $modal = $action->getSchema(
        Schema::make($livewire)->key('mountedActionSchema0'),
    );

    expect($modal)->not->toBeNull();

    $field = textInput($modal);

    expect($field->getLabel())->toBe('Visible name');
});

it('fills a field hint action label from the parent field path', function () {
    Lang::addLines([
        'filament/domain-form.form.components.user.schema.email.actions.hint_action.label' => 'Copy email',
    ], 'en');

    $livewire = app(DomainForm::class);
    $schema = Schema::make($livewire)->components([
        Section::make()
            ->key('user')
            ->schema([
                TextInput::make('email')
                    ->hintAction(Action::make('hint_action')),
            ]),
    ]);

    $section = $schema->getComponents()[0];
    $field = $section instanceof Section ? $section->getChildComponents()[0] : null;

    if (! $field instanceof TextInput) {
        throw new RuntimeException('The section did not contain a text input.');
    }

    $hintAction = hintAction($field);

    expect($hintAction->getLabel())->toBe('Copy email');
});

it('renders the compiled key when a field hint action label is missing in debug mode', function () {
    $livewire = app(DomainForm::class);
    $schema = Schema::make($livewire)->components([
        Section::make()
            ->key('user')
            ->schema([
                TextInput::make('email')
                    ->hintAction(Action::make('hint_action')),
            ]),
    ]);

    $section = $schema->getComponents()[0];
    $field = $section instanceof Section ? $section->getChildComponents()[0] : null;

    if (! $field instanceof TextInput) {
        throw new RuntimeException('The section did not contain a text input.');
    }

    $hintAction = hintAction($field);

    expect($hintAction->getLabel())->toBe('filament/domain-form.form.components.user.schema.email.actions.hint_action.label');
});

it('renders the compiled key when a required label is missing in debug mode', function () {
    $livewire = app(DomainForm::class);
    $schema = Schema::make($livewire)->components([
        TextInput::make('email'),
    ]);

    expect(textInput($schema)->getLabel())->toBe('filament/domain-form.form.components.email.label');
});

it('fills a keyed fieldset label from the message catalog', function () {
    Lang::addLines([
        'filament/domain-form.form.components.authorization.label' => 'Authorization',
        'filament/domain-form.form.components.authorization.schema.name.label' => 'Name',
    ], 'en');

    $livewire = app(DomainForm::class);
    $schema = Schema::make($livewire)->components([
        Fieldset::make()
            ->key('authorization', isInheritable: false)
            ->schema([
                TextInput::make('name'),
            ]),
    ]);

    $fieldset = $schema->getComponents()[0];
    $field = $fieldset instanceof Fieldset ? $fieldset->getChildComponents()[0] : null;

    expect($fieldset)->toBeInstanceOf(Fieldset::class)
        ->and($fieldset->getLabel())->toBe('Authorization')
        ->and($field)->toBeInstanceOf(TextInput::class)
        ->and($field->getLabel())->toBe('Name');
});

it('keeps an explicit fieldset label', function () {
    Lang::addLines([
        'filament/domain-form.form.components.authorization.label' => 'Authorization',
    ], 'en');

    $livewire = app(DomainForm::class);
    $schema = Schema::make($livewire)->components([
        Fieldset::make('Shown in English')
            ->key('authorization', isInheritable: false),
    ]);

    $fieldset = $schema->getComponents()[0];

    expect($fieldset)->toBeInstanceOf(Fieldset::class)
        ->and($fieldset->getLabel())->toBe('Shown in English');
});

it('does not invent a keyed fieldset label when the catalog omits it', function () {
    Lang::addLines([
        'filament/domain-form.form.components.authorization.schema.name.label' => 'Name',
    ], 'en');

    $livewire = app(DomainForm::class);
    $schema = Schema::make($livewire)->components([
        Fieldset::make()
            ->key('authorization', isInheritable: false)
            ->schema([
                TextInput::make('name'),
            ]),
    ]);

    $fieldset = $schema->getComponents()[0];

    expect($fieldset)->toBeInstanceOf(Fieldset::class)
        ->and($fieldset->getLabel())->toBeNull();
});

it('fills a keyed section description from the message catalog', function () {
    Lang::addLines([
        'filament/domain-form.form.components.authorization.heading' => 'Section Heading',
        'filament/domain-form.form.components.authorization.description' => 'Section Description Paragraph.',
        'filament/domain-form.form.components.authorization.schema.role.label' => 'Role',
    ], 'en');

    $livewire = app(DomainForm::class);
    $schema = Schema::make($livewire)->components([
        Section::make()
            ->key('authorization', isInheritable: false)
            ->schema([
                TextInput::make('role'),
            ]),
    ]);

    $section = $schema->getComponents()[0];
    $field = $section instanceof Section ? $section->getChildComponents()[0] : null;

    expect($section)->toBeInstanceOf(Section::class)
        ->and($section->getHeading())->toBe('Section Heading')
        ->and($section->getDescription())->toBe('Section Description Paragraph.')
        ->and($field)->toBeInstanceOf(TextInput::class)
        ->and($field->getLabel())->toBe('Role');
});

it('fills a keyed section heading without evaluating the heading to find the key', function () {
    Lang::addLines([
        'filament/domain-form.form.components.user.heading' => 'User',
        'filament/domain-form.form.components.user.schema.name.label' => 'Name',
    ], 'en');

    $livewire = app(DomainForm::class);
    $schema = Schema::make($livewire)->components([
        Section::make()
            ->key('user', isInheritable: false)
            ->schema([
                TextInput::make('name'),
            ]),
    ]);

    $section = $schema->getComponents()[0];
    $field = $section instanceof Section ? $section->getChildComponents()[0] : null;

    expect($section)->toBeInstanceOf(Section::class)
        ->and($section->getHeading())->toBe('User')
        ->and($field)->toBeInstanceOf(TextInput::class)
        ->and($field->getLabel())->toBe('Name');
});

it('does not invent a keyed section heading when the catalog omits it', function () {
    Lang::addLines([
        'filament/domain-form.form.components.user.schema.name.label' => 'Name',
    ], 'en');

    $livewire = app(DomainForm::class);
    $schema = Schema::make($livewire)->components([
        Section::make()
            ->key('user', isInheritable: false)
            ->schema([
                TextInput::make('name'),
            ]),
    ]);

    $section = $schema->getComponents()[0];
    $field = $section instanceof Section ? $section->getChildComponents()[0] : null;

    expect($section)->toBeInstanceOf(Section::class)
        ->and($section->getHeading())->toBeNull()
        ->and($field)->toBeInstanceOf(TextInput::class)
        ->and($field->getLabel())->toBe('Name');
});

it('does not put a heading-only section in the field path', function () {
    $livewire = app(DomainForm::class);
    $schema = Schema::make($livewire)->components([
        Section::make('Authorization')
            ->schema([
                TextInput::make('role'),
            ]),
    ]);

    $section = $schema->getComponents()[0];
    $field = $section instanceof Section ? $section->getChildComponents()[0] : null;

    expect($section)->toBeInstanceOf(Section::class)
        ->and($section->getHeading())->toBe('Authorization')
        ->and($field)->toBeInstanceOf(TextInput::class)
        ->and($field->getLabel())->toBe('filament/domain-form.form.components.role.label');
});

it('fills a wizard step label from the message catalog', function () {
    Lang::addLines([
        'filament/domain-form.form.components.first_step.label' => 'User',
        'filament/domain-form.form.components.first_step.schema.name.label' => 'Name',
    ], 'en');

    $livewire = app(DomainForm::class);
    $schema = Schema::make($livewire)->components([
        Wizard::make([
            Step::make('first_step')
                ->schema([
                    TextInput::make('name'),
                ]),
        ]),
    ]);

    $wizard = $schema->getComponents()[0];
    $step = $wizard instanceof Wizard ? $wizard->getChildComponents()[0] : null;
    $field = $step instanceof Step ? $step->getChildComponents()[0] : null;

    expect($step)->toBeInstanceOf(Step::class)
        ->and($step->getLabel())->toBe('User')
        ->and($field)->toBeInstanceOf(TextInput::class)
        ->and($field->getLabel())->toBe('Name');
});

it('keeps an explicit wizard step label', function () {
    Lang::addLines([
        'filament/domain-form.form.components.first_step.label' => 'User',
    ], 'en');

    $livewire = app(DomainForm::class);
    $schema = Schema::make($livewire)->components([
        Wizard::make([
            Step::make('first_step')
                ->label('Profile')
                ->schema([
                    TextInput::make('name'),
                ]),
        ]),
    ]);

    $wizard = $schema->getComponents()[0];
    $step = $wizard instanceof Wizard ? $wizard->getChildComponents()[0] : null;

    expect($step)->toBeInstanceOf(Step::class)
        ->and($step->getLabel())->toBe('Profile');
});

it('does not put an unkeyed wizard in the field path', function () {
    $livewire = app(DomainForm::class);
    $schema = Schema::make($livewire)->components([
        Wizard::make([
            Step::make('first_step')
                ->schema([
                    TextInput::make('name'),
                ]),
        ]),
    ]);

    $wizard = $schema->getComponents()[0];
    $step = $wizard instanceof Wizard ? $wizard->getChildComponents()[0] : null;
    $field = $step instanceof Step ? $step->getChildComponents()[0] : null;

    expect($field)->toBeInstanceOf(TextInput::class)
        ->and($field->getLabel())->toBe('filament/domain-form.form.components.first_step.schema.name.label');
});

it('fills a keyed wizard label from the message catalog', function () {
    Lang::addLines([
        'filament/domain-form.form.components.onboarding.label' => 'Onboarding',
        'filament/domain-form.form.components.onboarding.schema.first_step.label' => 'User',
    ], 'en');

    $livewire = app(DomainForm::class);
    $schema = Schema::make($livewire)->components([
        Wizard::make([
            Step::make('first_step')
                ->schema([
                    TextInput::make('name'),
                ]),
        ])->key('onboarding', isInheritable: false),
    ]);

    $wizard = $schema->getComponents()[0];
    $step = $wizard instanceof Wizard ? $wizard->getChildComponents()[0] : null;

    expect($wizard)->toBeInstanceOf(Wizard::class)
        ->and($wizard->getLabel())->toBe('Onboarding')
        ->and($step)->toBeInstanceOf(Step::class)
        ->and($step->getLabel())->toBe('User');
});

it('fills a tab label from the message catalog', function () {
    Lang::addLines([
        'filament/domain-form.form.components.user_tabs.label' => 'Details',
        'filament/domain-form.form.components.user_tabs.schema.user.label' => 'User',
        'filament/domain-form.form.components.user_tabs.schema.user.schema.name.label' => 'Name',
    ], 'en');

    $livewire = app(DomainForm::class);
    $schema = Schema::make($livewire)->components([
        Tabs::make('user_tabs')
            ->tabs([
                Tab::make('user')
                    ->schema([
                        TextInput::make('name'),
                    ]),
            ]),
    ]);

    $tabs = $schema->getComponents()[0];
    $tab = $tabs instanceof Tabs ? $tabs->getChildComponents()[0] : null;
    $field = $tab instanceof Tab ? $tab->getChildComponents()[0] : null;

    expect($tabs)->toBeInstanceOf(Tabs::class)
        ->and($tabs->getLabel())->toBe('Details')
        ->and($tab)->toBeInstanceOf(Tab::class)
        ->and($tab->getLabel())->toBe('User')
        ->and($field)->toBeInstanceOf(TextInput::class)
        ->and($field->getLabel())->toBe('Name');
});

it('fills an empty state heading from the message catalog', function () {
    Lang::addLines([
        'filament/domain-form.form.components.user_empty_state.heading' => 'No users yet',
        'filament/domain-form.form.components.user_empty_state.description' => 'Get started by creating a new user.',
    ], 'en');

    $livewire = app(DomainForm::class);
    $schema = Schema::make($livewire)->components([
        EmptyState::make('user_empty_state'),
    ]);

    $emptyState = $schema->getComponents()[0];

    expect($emptyState)->toBeInstanceOf(EmptyState::class)
        ->and($emptyState->getHeading())->toBe('No users yet')
        ->and($emptyState->getDescription())->toBe('Get started by creating a new user.');
});

it('keeps an explicit empty state heading and description', function () {
    Lang::addLines([
        'filament/domain-form.form.components.user_empty_state.heading' => 'No users yet',
        'filament/domain-form.form.components.user_empty_state.description' => 'Get started by creating a new user.',
    ], 'en');

    $livewire = app(DomainForm::class);
    $schema = Schema::make($livewire)->components([
        EmptyState::make('user_empty_state')
            ->heading('Nobody here')
            ->description('Try again later.'),
    ]);

    $emptyState = $schema->getComponents()[0];

    expect($emptyState)->toBeInstanceOf(EmptyState::class)
        ->and($emptyState->getHeading())->toBe('Nobody here')
        ->and($emptyState->getDescription())->toBe('Try again later.');
});

it('does not invent an empty state description when the catalog omits it', function () {
    Lang::addLines([
        'filament/domain-form.form.components.user_empty_state.heading' => 'No users yet',
    ], 'en');

    $livewire = app(DomainForm::class);
    $schema = Schema::make($livewire)->components([
        EmptyState::make('user_empty_state'),
    ]);

    $emptyState = $schema->getComponents()[0];

    expect($emptyState)->toBeInstanceOf(EmptyState::class)
        ->and($emptyState->getHeading())->toBe('No users yet')
        ->and($emptyState->getDescription())->toBeNull();
});

it('keeps an explicit tab label', function () {
    Lang::addLines([
        'filament/domain-form.form.components.user_tabs.schema.user.label' => 'User',
    ], 'en');

    $livewire = app(DomainForm::class);
    $schema = Schema::make($livewire)->components([
        Tabs::make('user_tabs')
            ->tabs([
                Tab::make('user')
                    ->label('Profile')
                    ->schema([
                        TextInput::make('name'),
                    ]),
            ]),
    ]);

    $tabs = $schema->getComponents()[0];
    $tab = $tabs instanceof Tabs ? $tabs->getChildComponents()[0] : null;

    expect($tab)->toBeInstanceOf(Tab::class)
        ->and($tab->getLabel())->toBe('Profile');
});

it('leaves components unbound when the livewire owner is not a catalog', function () {
    $schema = Schema::make()->components([
        TextInput::make('email'),
    ]);

    expect(ResolutionExplainer::explain(textInput($schema))->decision)->toBe(ResolutionOutcome::NoCatalog);
});

it('fills a schema action notification title and body from the message catalog using status', function () {
    Lang::addLines([
        'filament/domain-form.form.components.actions.action.notifications.success.title' => 'Saved',
        'filament/domain-form.form.components.actions.action.notifications.success.body' => 'The record was saved.',
        'filament/domain-form.form.components.actions.action.notifications.danger.title' => 'Failed',
        'filament/domain-form.form.components.actions.action.notifications.danger.body' => 'The record was not saved.',
        'filament/domain-form.form.components.actions.action.notifications.info.title' => 'Heads up',
    ], 'en');

    $livewire = app(DomainForm::class);
    $sent = [];

    $schema = Schema::make($livewire)->components([
        Action::make('action')
            ->action(function () use (&$sent): void {
                $sent['success'] = Notification::make()->success();
                $sent['danger'] = Notification::make()->danger();
                $sent['info'] = Notification::make()->info();
            }),
    ]);

    callSchemaAction(schemaAction($schema));

    expect($sent['success']->getTitle())->toBe('Saved')
        ->and($sent['success']->getBody())->toBe('The record was saved.');

    expect($sent['danger']->getTitle())->toBe('Failed')
        ->and($sent['danger']->getBody())->toBe('The record was not saved.');

    expect($sent['info']->getTitle())->toBe('Heads up')
        ->and($sent['info']->getBody())->toBeNull();
});

it('keeps an explicit notification title', function () {
    Lang::addLines([
        'filament/domain-form.form.components.actions.action.notifications.success.title' => 'Saved',
    ], 'en');

    $livewire = app(DomainForm::class);
    $sent = null;

    $schema = Schema::make($livewire)->components([
        Action::make('action')
            ->action(function () use (&$sent): void {
                $sent = Notification::make()
                    ->success()
                    ->title('Done');
            }),
    ]);

    callSchemaAction(schemaAction($schema));

    expect($sent)->toBeInstanceOf(Notification::class)
        ->and($sent->getTitle())->toBe('Done');
});

it('does not invent a notification body when the catalog omits it', function () {
    Lang::addLines([
        'filament/domain-form.form.components.actions.action.notifications.success.title' => 'Saved',
    ], 'en');

    $livewire = app(DomainForm::class);
    $sent = null;

    $schema = Schema::make($livewire)->components([
        Action::make('action')
            ->action(function () use (&$sent): void {
                $sent = Notification::make()->success();
            }),
    ]);

    callSchemaAction(schemaAction($schema));

    expect($sent)->toBeInstanceOf(Notification::class)
        ->and($sent->getTitle())->toBe('Saved')
        ->and($sent->getBody())->toBeNull();
});

it('does not bind a notification that has no status', function () {
    Lang::addLines([
        'filament/domain-form.form.components.actions.action.notifications.success.title' => 'Saved',
    ], 'en');

    $livewire = app(DomainForm::class);
    $sent = null;

    $schema = Schema::make($livewire)->components([
        Action::make('action')
            ->action(function () use (&$sent): void {
                $sent = Notification::make();
            }),
    ]);

    callSchemaAction(schemaAction($schema));

    expect($sent)->toBeInstanceOf(Notification::class)
        ->and($sent->getTitle())->toBeNull()
        ->and($sent->getBody())->toBeNull();
});

it('fills a page header action label from the message catalog', function () {
    Lang::addLines([
        'filament/catalog-owner.pages.edit-user.actions.first_action.label' => 'First action',
    ], 'en');

    $livewire = app(EditUser::class);
    $action = Action::make('first_action');
    $action->livewire($livewire);

    expect($action->getLabel())->toBe('First action');
});

it('fills extra modal footer actions under the owning page action', function () {
    Lang::addLines([
        'filament/catalog-owner.pages.edit-user.actions.first_action.extra_modal_footer_actions.second_action.label' => 'Second action',
    ], 'en');

    $livewire = app(EditUser::class);
    $action = Action::make('first_action')
        ->extraModalFooterActions([
            Action::make('second_action'),
        ]);
    $action->livewire($livewire);

    $footer = $action->getExtraModalFooterActions()['second_action'] ?? null;

    expect($footer)->toBeInstanceOf(Action::class)
        ->and($footer->getLabel())->toBe('Second action');
});

it('fills a page action schema field under schema.components', function () {
    Lang::addLines([
        'filament/catalog-owner.pages.edit-user.actions.first_action.schema.components.name.label' => 'Name',
    ], 'en');

    $livewire = app(EditUser::class);
    $action = Action::make('first_action')
        ->schema([
            TextInput::make('name'),
        ]);
    $action->livewire($livewire);
    $livewire->withMountedAction($action);

    $modal = $action->getSchema(
        Schema::make($livewire)->key('mountedActionSchema0'),
    );

    expect($modal)->not->toBeNull()
        ->and(textInput($modal)->getLabel())->toBe('Name');
});

it('does not bind a notification that has no owning action', function () {
    Lang::addLines([
        'filament/domain-form.form.components.actions.action.notifications.success.title' => 'Saved',
    ], 'en');

    $notification = Notification::make()->success();

    expect($notification->getTitle())->toBeNull()
        ->and($notification->getBody())->toBeNull();
});

function schemaAction(Schema $schema): Action
{
    $component = $schema->getComponents()[0] ?? null;

    if (! $component instanceof Action) {
        throw new RuntimeException('The schema did not contain an action.');
    }

    return $component;
}

function callSchemaAction(Action $action): void
{
    $action->callBefore();

    try {
        $action->call();
    } finally {
        $action->callAfter();
    }
}

function textInput(Schema $schema): TextInput
{
    $component = $schema->getComponents()[0] ?? null;

    if (! $component instanceof TextInput) {
        throw new RuntimeException('The schema did not contain a text input.');
    }

    return $component;
}

function hintAction(TextInput $field): Action
{
    $schema = $field->getChildSchema(TextInput::AFTER_LABEL_SCHEMA_KEY);

    foreach ($schema?->getComponents() ?? [] as $component) {
        if ($component instanceof Action) {
            return $component;
        }
    }

    throw new RuntimeException('The field did not contain a hint action.');
}

function sectionTextInput(Schema $schema): TextInput
{
    $section = $schema->getComponents()[0] ?? null;
    $field = $section instanceof Section ? $section->getChildComponents()[0] : null;

    if (! $field instanceof TextInput) {
        throw new RuntimeException('The section did not contain a text input.');
    }

    return $field;
}

function chromeText(TextInput $field, string $schemaKey): Text
{
    $schema = $field->getChildSchema($schemaKey);
    $component = $schema?->getComponents()[0] ?? null;

    if (! $component instanceof Text) {
        throw new RuntimeException("The field did not contain chrome text for [{$schemaKey}].");
    }

    return $component;
}

function tableFilter(Table $table): Filter
{
    $filter = $table->getFilter('is_featured', withHidden: true);

    if (! $filter instanceof Filter) {
        throw new RuntimeException('The table did not contain the is_featured filter.');
    }

    return $filter;
}

function tableAction(array $actions): Action
{
    $action = $actions[0] ?? null;

    if (! $action instanceof Action) {
        throw new RuntimeException('The table did not contain an action.');
    }

    return $action;
}

it('fills a placeholder in a bound label from messageReplace', function () {
    Lang::addLines([
        'filament/domain-form.form.components.terms.body' => 'Read our :policy before you continue.',
    ], 'en');

    $livewire = app(DomainForm::class);
    $schema = Schema::make($livewire)->components([
        Text::make('terms')->messageReplace(['policy' => 'privacy policy']),
    ]);

    $text = $schema->getComponents()[0];

    expect((string) $text->getContent())->toBe('Read our privacy policy before you continue.');
});

it('resolves a messageReplace closure at render time, not when the component is built', function () {
    Lang::addLines([
        'filament/domain-form.form.components.counter.body' => 'Seen :count times.',
    ], 'en');

    // an arrow function captures by value, so the counter has to be shared state
    $counter = new class
    {
        public int $value = 1;
    };

    $livewire = app(DomainForm::class);
    $schema = Schema::make($livewire)->components([
        Text::make('counter')->messageReplace(['count' => fn (): int => $counter->value]),
    ]);

    $text = $schema->getComponents()[0];

    expect((string) $text->getContent())->toBe('Seen 1 times.');

    $counter->value = 7;

    expect((string) $text->getContent())->toBe('Seen 7 times.');
});

it('leaves a bound label alone when no replacement is declared', function () {
    Lang::addLines([
        'filament/domain-form.form.components.plain.body' => 'No placeholders here.',
    ], 'en');

    $livewire = app(DomainForm::class);
    $schema = Schema::make($livewire)->components([
        Text::make('plain'),
    ]);

    expect((string) $schema->getComponents()[0]->getContent())->toBe('No placeholders here.');
});

it('does not apply replacements to a label the catalog never supplied', function () {
    $livewire = app(DomainForm::class);
    $schema = Schema::make($livewire)->components([
        TextInput::make('unbound')->messageReplace(['name' => 'Ada']),
    ]);

    // on_missing is debug in these tests, so the compiled key is the visible text
    expect($schema->getComponents()[0]->getLabel())
        ->toBe('filament/domain-form.form.components.unbound.label');
});

it('fills a section heading built outside the schema builder', function () {
    Lang::addLines([
        'identity/user-chrome.form.components.account.heading' => 'Your account',
    ], 'en');

    $owner = app(DomainForm::class);
    app(MessageBinder::class)->setCatalogId($owner, 'identity.user-chrome');

    $content = ChromeForm::make();
    $mounted = Schema::make($owner)->components([$content])->getComponents();
    $section = $mounted[0]->getChildSchemas()['default']->getComponents()[0];

    expect($section->getHeading())->toBe('Your account');
});
