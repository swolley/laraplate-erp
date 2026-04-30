<?php

declare(strict_types=1);

use Filament\Schemas\Schema;
use Modules\ERP\Filament\Resources\Contacts\ContactResource;
use Modules\ERP\Filament\Resources\Customers\CustomerResource;
use Modules\ERP\Filament\Resources\Leads\LeadResource;
use Modules\ERP\Filament\Resources\Opportunities\OpportunityResource;
use Modules\ERP\Filament\Resources\Projects\ProjectResource;
use Modules\ERP\Filament\Resources\Quotations\QuotationResource;
use Modules\ERP\Filament\Resources\SalesOrders\SalesOrderResource;

it('defines Filament pages for customer resource', function (): void {
    expect(CustomerResource::getPages())
        ->toHaveKey('index')
        ->toHaveKey('create')
        ->toHaveKey('edit');
});

it('customer resource form includes core fields', function (): void {
    $schema = CustomerResource::form(new Schema());
    $names = array_map(
        static fn ($component): ?string => method_exists($component, 'getName') ? $component->getName() : null,
        $schema->getComponents(),
    );

    expect($names)->toContain('company_id')
        ->and($names)->toContain('name')
        ->and($names)->toContain('is_active');
});

it('defines Filament pages for contact resource', function (): void {
    expect(ContactResource::getPages())
        ->toHaveKey('index')
        ->toHaveKey('create')
        ->toHaveKey('edit');
});

it('contact resource form includes customer linkage field', function (): void {
    $schema = ContactResource::form(new Schema());
    $names = array_map(
        static fn ($component): ?string => method_exists($component, 'getName') ? $component->getName() : null,
        $schema->getComponents(),
    );

    expect($names)->toContain('company_id')
        ->and($names)->toContain('customer_ids');
});

it('defines Filament pages for quotation resource', function (): void {
    expect(QuotationResource::getPages())
        ->toHaveKey('index')
        ->toHaveKey('create')
        ->toHaveKey('edit');
});

it('quotation resource form includes line items repeater', function (): void {
    $schema = QuotationResource::form(new Schema());
    $names = array_map(
        static fn ($component): ?string => method_exists($component, 'getName') ? $component->getName() : null,
        $schema->getComponents(),
    );

    expect($names)->toContain('line_items')
        ->and($names)->toContain('customer_id')
        ->and($names)->toContain('opportunity_id');
});

it('defines Filament pages for lead resource', function (): void {
    expect(LeadResource::getPages())
        ->toHaveKey('index')
        ->toHaveKey('create')
        ->toHaveKey('edit');
});

it('defines Filament pages for opportunity resource', function (): void {
    expect(OpportunityResource::getPages())
        ->toHaveKey('index')
        ->toHaveKey('create')
        ->toHaveKey('edit');
});

it('defines Filament pages for sales order resource', function (): void {
    expect(SalesOrderResource::getPages())
        ->toHaveKey('index')
        ->toHaveKey('create')
        ->toHaveKey('edit');
});

it('sales order resource form includes line items repeater', function (): void {
    $schema = SalesOrderResource::form(new Schema());
    $names = array_map(
        static fn ($component): ?string => method_exists($component, 'getName') ? $component->getName() : null,
        $schema->getComponents(),
    );

    expect($names)->toContain('line_items')
        ->and($names)->toContain('customer_id');
});

it('defines Filament pages for project resource', function (): void {
    expect(ProjectResource::getPages())
        ->toHaveKey('index')
        ->toHaveKey('create')
        ->toHaveKey('edit');
});

it('project resource form includes validity fields', function (): void {
    $schema = ProjectResource::form(new Schema());
    $names = array_map(
        static fn ($component): ?string => method_exists($component, 'getName') ? $component->getName() : null,
        $schema->getComponents(),
    );

    expect($names)->toContain('valid_from')
        ->and($names)->toContain('valid_to');
});
