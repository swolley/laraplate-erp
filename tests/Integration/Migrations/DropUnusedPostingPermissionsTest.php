<?php

declare(strict_types=1);

use Modules\Core\Authorization\PermissionManifest;
use Modules\ERP\Authorization\ERPPermissions;
use Modules\ERP\Models\DeliveryNote;
use Modules\ERP\Models\Invoice;

/**
 * `post` and `unpost` were once seeded on five models that are never posted. A
 * migration used to delete those ten rows; the declaration that produced them is
 * gone instead, so a fresh database never writes them and there is nothing left
 * to delete. What has to stay true is the law the migration enforced: posting
 * belongs to the invoice and the delivery note, and to no other document.
 */
it('declares posting on the invoice and the delivery note', function (): void {
    $operations = ERPPermissions::operations();

    expect($operations[Invoice::class] ?? [])->toContain('post')->toContain('unpost')
        ->and($operations[DeliveryNote::class] ?? [])->toContain('post')->toContain('unpost');
});

it('declares posting on nothing else', function (): void {
    $posting_models = [];

    foreach (ERPPermissions::operations() as $model_class => $operations) {
        if (in_array('post', $operations, true) || in_array('unpost', $operations, true)) {
            $posting_models[] = $model_class;
        }
    }

    sort($posting_models);

    expect($posting_models)->toBe([DeliveryNote::class, Invoice::class]);
});

/**
 * The five models that lost posting keep the verbs they really answer to: a
 * fiscal period closes and reopens, a journal entry reverses, a sales order is
 * amended.
 */
it('leaves the live verbs on those documents alone', function (): void {
    $names = app(PermissionManifest::class)->namesFor('ERP');

    expect($names)
        ->toContain('default.erp_fiscal_periods.close')
        ->toContain('default.erp_fiscal_periods.reopen')
        ->toContain('default.erp_journal_entries.reverse')
        ->toContain('default.erp_sales_orders.amend')
        ->toContain('default.erp_quotations.unlock')
        ->toContain('default.erp_document_sequences.reset');
});

it('generates no posting permission for a document that is not posted', function (): void {
    $names = app(PermissionManifest::class)->namesFor('ERP');

    expect($names)
        ->not->toContain('default.erp_quotations.post')
        ->not->toContain('default.erp_sales_orders.post')
        ->not->toContain('default.erp_journal_entries.post')
        ->not->toContain('default.erp_fiscal_periods.post')
        ->not->toContain('default.erp_document_sequences.post');
});
