<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\ERP\Casts\MatchStatus;
use Modules\ERP\Casts\TaxKind;
use Modules\ERP\Contracts\CurrencyConverter;
use Modules\ERP\Models\BankStatementLine;
use Modules\ERP\Models\Company;
use Modules\ERP\Models\DeliveryNote;
use Modules\ERP\Models\DeliveryNoteLine;
use Modules\ERP\Models\ExchangeRate;
use Modules\ERP\Models\InvoiceLine;
use Modules\ERP\Models\Payment;
use Modules\ERP\Models\PurchaseOrderLine;
use Modules\ERP\Models\TaxCode;
use Modules\ERP\Services\Banking\BankDifferenceJournalService;
use Modules\ERP\Services\Inventory\DeliveryNoteCogsJournalService;
use Modules\ERP\Services\Purchasing\ThreeWayMatchService;
use Modules\ERP\Services\Taxation\TaxLineCalculator;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config()->set('database.connections.erp-helper-secondary', [
        'driver' => 'sqlite',
        'database' => ':memory:',
        'prefix' => '',
        'foreign_key_constraints' => false,
    ]);

    $schema = Schema::connection('erp-helper-secondary');
    $schema->create((new PurchaseOrderLine)->getTable(), function (Blueprint $table): void {
        $table->increments('id');
        $table->unsignedInteger('purchase_order_id');
        $table->string('name');
        $table->decimal('qty_ordered', 16, 4);
        $table->decimal('qty_received', 16, 4)->default(0);
        $table->decimal('qty_returned', 16, 4)->default(0);
        $table->decimal('unit_price', 16, 4)->nullable();
        $table->timestamps();
        $table->boolean('is_deleted')->default(false);
    });
    $schema->create((new ExchangeRate)->getTable(), function (Blueprint $table): void {
        $table->increments('id');
        $table->string('from_currency', 3);
        $table->string('to_currency', 3);
        $table->decimal('rate', 16, 8);
        $table->date('rate_date');
        $table->string('source')->nullable();
        $table->timestamps();
        $table->boolean('is_deleted')->default(false);
    });
    $schema->create((new TaxCode)->getTable(), function (Blueprint $table): void {
        $table->increments('id');
        $table->unsignedInteger('company_id');
        $table->string('code');
        $table->string('kind');
        $table->string('country', 2);
        $table->decimal('rate', 8, 4);
        $table->string('label');
        $table->boolean('is_active');
        $table->date('effective_from');
        $table->timestamps();
        $table->boolean('is_deleted')->default(false);
    });
});

it('resolves three-way match participants on the invoice line connection', function (): void {
    Schema::connection('erp-helper-secondary')->getConnection()
        ->table((new PurchaseOrderLine)->getTable())
        ->insert([
            'id' => 9401,
            'purchase_order_id' => 9401,
            'name' => 'Secondary item',
            'qty_ordered' => '2.0000',
            'qty_received' => '0.0000',
            'qty_returned' => '0.0000',
            'unit_price' => '15.0000',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

    $invoice_line = (new InvoiceLine)->setConnection('erp-helper-secondary');
    $invoice_line->purchase_order_line_id = 9401;
    $invoice_line->quantity = '2.0000';
    $invoice_line->unit_price = '15.0000';

    $result = app(ThreeWayMatchService::class)->validate($invoice_line);

    expect($result['status'])->toBe(MatchStatus::Matched)
        ->and(PurchaseOrderLine::query()->whereKey(9401)->exists())->toBeFalse()
        ->and(config('database.default'))->not->toBe('erp-helper-secondary');
});

it('resolves database exchange rates from a trusted owner connection', function (): void {
    Schema::connection('erp-helper-secondary')->getConnection()
        ->table((new ExchangeRate)->getTable())
        ->insert([
            'id' => 9402,
            'from_currency' => 'USD',
            'to_currency' => 'EUR',
            'rate' => '0.92000000',
            'rate_date' => '2026-07-15',
            'source' => 'secondary',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

    $owner = (new Company)->setConnection('erp-helper-secondary');
    $result = app(CurrencyConverter::class)->convert(
        $owner,
        'USD',
        'EUR',
        '100.00',
        CarbonImmutable::parse('2026-07-20'),
    );

    expect($result)->toBe(['rate' => 0.92, 'amount' => 92.0])
        ->and(ExchangeRate::query()->whereKey(9402)->exists())->toBeFalse()
        ->and(config('database.default'))->not->toBe('erp-helper-secondary');
});

it('resolves active tax codes on the company connection', function (): void {
    Schema::connection('erp-helper-secondary')->getConnection()
        ->table((new TaxCode)->getTable())
        ->insert([
            'id' => 9403,
            'company_id' => 9403,
            'code' => 'VAT22',
            'kind' => TaxKind::Vat->value,
            'country' => 'IT',
            'rate' => '22.0000',
            'label' => 'VAT 22%',
            'is_active' => true,
            'effective_from' => '2026-01-01',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

    $company = (new Company)->setConnection('erp-helper-secondary');
    $company->id = 9403;
    $company->exists = true;

    $tax_code = app(TaxLineCalculator::class)->resolveActiveAt(
        $company,
        'VAT22',
        CarbonImmutable::parse('2026-07-20'),
    );

    expect($tax_code->getConnectionName())->toBe('erp-helper-secondary')
        ->and($tax_code->id)->toBe(9403)
        ->and(TaxCode::query()->whereKey(9403)->exists())->toBeFalse()
        ->and(config('database.default'))->not->toBe('erp-helper-secondary');
});

it('rejects mixed delivery note line connections before an early return', function (): void {
    $delivery_note = (new DeliveryNote)->setConnection('erp-helper-secondary');
    $delivery_note->cogs_journal_entry_id = 9404;

    $primary_line = new DeliveryNoteLine;
    $secondary_line = (new DeliveryNoteLine)->setConnection('erp-helper-secondary');

    expect(fn () => app(DeliveryNoteCogsJournalService::class)->postForDeliveryNoteIfNeeded(
        $delivery_note,
        collect([$primary_line, $secondary_line]),
    ))->toThrow(LogicException::class);
});

it('rejects mixed bank difference participants before issuing queries', function (): void {
    $company = (new Company)->setConnection('erp-helper-secondary');
    $company->id = 9405;
    $company->exists = true;

    $line = new BankStatementLine;
    $line->id = 9405;
    $line->bank_statement_id = 9405;
    $line->exists = true;

    $payment = (new Payment)->setConnection('erp-helper-secondary');
    $payment->id = 9405;
    $payment->exists = true;

    $primary = DB::connection((string) config('database.default'));
    $secondary = DB::connection('erp-helper-secondary');
    $primary->flushQueryLog();
    $secondary->flushQueryLog();
    $primary->enableQueryLog();
    $secondary->enableQueryLog();

    try {
        app(BankDifferenceJournalService::class)->postDifference(
            $company,
            $line,
            $payment,
            '1.0000',
            9405,
            9405,
        );

        $this->fail('Expected mixed connection participants to be rejected.');
    } catch (LogicException) {
        expect($primary->getQueryLog())->toBeEmpty()
            ->and($secondary->getQueryLog())->toBeEmpty();
    }
});
