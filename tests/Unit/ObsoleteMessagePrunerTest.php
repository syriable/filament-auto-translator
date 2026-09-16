<?php

declare(strict_types=1);

use Syriable\Translation\Catalog\ObsoleteMessagePruner;

it('removes a form key whose component is no longer live', function () {
    $pruner = new ObsoleteMessagePruner;

    expect($pruner->isOrphan(
        ['form', 'components', 'authorization', 'schema', 'or', 'body'],
        ['form.components.authorization.schema.name' => true, 'form.components.authorization' => true],
        [],
        ['form' => true],
    ))->toBeTrue();
});

it('keeps optional slots under a component that is still live', function () {
    $pruner = new ObsoleteMessagePruner;

    expect($pruner->isOrphan(
        ['form', 'components', 'authorization', 'schema', 'name', 'placeholder'],
        ['form.components.authorization.schema.name' => true],
        [],
        ['form' => true],
    ))->toBeFalse();
});

it('keeps option relatives under a live field', function () {
    $pruner = new ObsoleteMessagePruner;

    expect($pruner->isOrphan(
        ['form', 'components', 'role', 'options', 'admin'],
        ['form.components.role' => true],
        [],
        ['form' => true],
    ))->toBeFalse();
});

it('keeps page action keys when the page is still registered', function () {
    $pruner = new ObsoleteMessagePruner;

    expect($pruner->isOrphan(
        ['pages', 'create-user', 'actions', 'publish', 'label'],
        [],
        ['create-user' => true],
        ['pages' => true],
    ))->toBeFalse();
});

it('removes a page tree when that page is no longer registered', function () {
    $pruner = new ObsoleteMessagePruner;

    expect($pruner->isOrphan(
        ['pages', 'old-page', 'title'],
        [],
        ['create-user' => true],
        ['pages' => true],
    ))->toBeTrue();
});

it('does not prune form keys when the form walk did not run', function () {
    $pruner = new ObsoleteMessagePruner;

    expect($pruner->isOrphan(
        ['form', 'components', 'name', 'label'],
        [],
        [],
        ['table' => true],
    ))->toBeFalse();
});

it('does not prune resource chrome keys', function () {
    $pruner = new ObsoleteMessagePruner;

    expect($pruner->isOrphan(
        ['model_label'],
        [],
        [],
        ['form' => true, 'pages' => true],
    ))->toBeFalse();
});
