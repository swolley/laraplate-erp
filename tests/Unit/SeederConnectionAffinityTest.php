<?php

declare(strict_types=1);

use Modules\ERP\Database\Seeders\DevERPDatabaseSeeder;
use Modules\ERP\Database\Seeders\ERPDatabaseSeeder;
use Modules\ERP\Database\Seeders\ItalianTaxCodesSeeder;

it('derives ERP seeder schema checks and transactions from model owners', function (): void {
    $erp_source = file_get_contents((new ReflectionClass(ERPDatabaseSeeder::class))->getFileName());
    $dev_source = file_get_contents((new ReflectionClass(DevERPDatabaseSeeder::class))->getFileName());
    $tax_source = file_get_contents((new ReflectionClass(ItalianTaxCodesSeeder::class))->getFileName());

    expect($erp_source)->not->toContain('Schema::hasTable(')
        ->and($erp_source)->not->toContain('DB::transaction(')
        ->and($dev_source)->not->toContain('Schema::hasTable(')
        ->and($dev_source)->not->toContain('DB::transaction(')
        ->and($tax_source)->not->toContain('Schema::hasTable(');
});
