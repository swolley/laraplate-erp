<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Modules\ERP\Models\Company;
use Modules\ERP\Models\ReportSnapshot;
use Modules\ERP\Services\Reporting\ReportPdfExporter;
use Modules\ERP\Services\Reporting\ReportSnapshotService;

uses(RefreshDatabase::class);

function createReportSnapshotCompany(): Company
{
    return Company::query()->create([
        'slug' => 'report-snapshot-' . uniqid(),
        'name' => 'Report Snapshot Co',
        'fiscal_country' => 'IT',
        'default_currency' => 'EUR',
    ]);
}

it('renders tabular report data as a simple PDF document', function (): void {
    $pdf = app(ReportPdfExporter::class)->render('Trial balance', [
        ['account' => '1000', 'balance' => '1200.0000'],
    ]);

    expect($pdf)->toStartWith('%PDF-1.4')
        ->and($pdf)->toContain('xref')
        ->and($pdf)->toContain('%%EOF');
});

it('archives report snapshots with csv pdf payload and immutable hash', function (): void {
    $company = createReportSnapshotCompany();

    $snapshot = app(ReportSnapshotService::class)->archive(
        company_id: (int) $company->id,
        report_key: 'trial_balance',
        title: 'Trial balance',
        parameters: ['as_of' => '2026-07-16'],
        payload: ['rows' => [['account_code' => '1000', 'balance' => '1200.0000']]],
        csv_content: "Account,Balance\n1000,1200.0000\n",
    );

    expect($snapshot)->toBeInstanceOf(ReportSnapshot::class)
        ->and($snapshot->content_hash)->not->toBe('')
        ->and($snapshot->csv_content)->toContain('Account,Balance')
        ->and(app(ReportSnapshotService::class)->decodedPdf($snapshot))->toStartWith('%PDF-1.4');

    $snapshot->title = 'Changed';

    expect(fn () => $snapshot->save())->toThrow(ValidationException::class);
});

it('creates report snapshots through the console command', function (): void {
    $company = createReportSnapshotCompany();

    $this->artisan('erp:reports:snapshot', [
        'report' => 'trial_balance',
        '--company' => (string) $company->id,
        '--to' => '2026-07-16',
    ])->assertSuccessful();

    $snapshot = ReportSnapshot::query()->firstOrFail();

    expect($snapshot->report_key)->toBe('trial_balance')
        ->and($snapshot->parameters['as_of'])->toBe('2026-07-16')
        ->and($snapshot->csv_content)->toContain('Account code')
        ->and($snapshot->pdf_content)->not->toBeNull();
});

it('supports report snapshot command dry runs without archiving', function (): void {
    $company = createReportSnapshotCompany();

    $this->artisan('erp:reports:snapshot', [
        'report' => 'trial_balance',
        '--company' => (string) $company->id,
        '--to' => '2026-07-16',
        '--dry-run' => true,
    ])->assertSuccessful();

    expect(ReportSnapshot::query()->count())->toBe(0);
});
