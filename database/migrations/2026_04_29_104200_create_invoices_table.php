<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\ERP\Casts\InvoiceDirection;
use Modules\ERP\Helpers\ERPMigrateUtils;
use Modules\Core\Helpers\MigrateUtils;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table): void {
            $table->id();
            ERPMigrateUtils::companyForeign($table);
            $table->enum('direction', array_map(
                static fn (InvoiceDirection $d): string => $d->value,
                InvoiceDirection::cases(),
            ));
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
        Schema::dropIfExists('invoices');
    }
};
