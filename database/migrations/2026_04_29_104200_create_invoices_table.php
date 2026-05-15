<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Helpers\MigrateUtils;
use Modules\ERP\Casts\InvoiceDirection;
use Modules\ERP\Enums\ERPTables;
use Modules\ERP\Helpers\ERPMigrateUtils;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(ERPTables::Invoices->value, function (Blueprint $table): void {
            $table->id();
            ERPMigrateUtils::companyForeign($table);
            $table->enum('direction', array_map(
                static fn (InvoiceDirection $d): string => $d->value,
                InvoiceDirection::cases(),
            ));
            $table->string('reference', 64)->nullable()->comment('Assigned by DocumentNumberAllocator at posting time');
            $table->char('currency', 3);
            $table->timestamp('posted_at')->nullable()->index();
            $table->text('notes')->nullable();

            MigrateUtils::timestamps(
                $table,
                hasCreateUpdate: true,
                hasSoftDelete: true,
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(ERPTables::Invoices->value);
    }
};
