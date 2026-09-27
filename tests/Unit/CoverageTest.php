<?php

declare(strict_types=1);

use Syriable\FilamentAutoTranslator\Scanning\Coverage;

it('removes a form key whose component is no longer live', function () {
    expect((new Coverage(['form.components.authorization.schema.name' => true, 'form.components.authorization' => true], [], ['form' => true]))->isOrphaned(['form', 'components', 'authorization', 'schema', 'or', 'body']))->toBeTrue();
});

it('keeps optional slots under a component that is still live', function () {
    expect((new Coverage(['form.components.authorization.schema.name' => true], [], ['form' => true]))->isOrphaned(['form', 'components', 'authorization', 'schema', 'name', 'placeholder']))->toBeFalse();
});

it('keeps option relatives under a live field', function () {
    expect((new Coverage(['form.components.role' => true], [], ['form' => true]))->isOrphaned(['form', 'components', 'role', 'options', 'admin']))->toBeFalse();
});

it('keeps page action keys when the page is still registered', function () {
    expect((new Coverage([], ['create-user' => true], ['pages' => true]))->isOrphaned(['pages', 'create-user', 'actions', 'publish', 'label']))->toBeFalse();
});

it('removes a page tree when that page is no longer registered', function () {
    expect((new Coverage([], ['create-user' => true], ['pages' => true]))->isOrphaned(['pages', 'old-page', 'title']))->toBeTrue();
});

it('does not prune form keys when the form walk did not run', function () {
    expect((new Coverage([], [], ['table' => true]))->isOrphaned(['form', 'components', 'name', 'label']))->toBeFalse();
});

it('does not prune resource chrome keys', function () {
    expect((new Coverage([], [], ['form' => true, 'pages' => true]))->isOrphaned(['model_label']))->toBeFalse();
});

it('never prunes a scope whose builder failed', function () {
    expect((new Coverage([], [], []))->isOrphaned(['form', 'components', 'gone', 'label']))->toBeFalse();
});

it('never prunes page keys of a domain that registers no resource pages', function () {
    expect((new Coverage([], [], ['form' => true]))->isOrphaned(['pages', 'old-page', 'title']))->toBeFalse();
});
