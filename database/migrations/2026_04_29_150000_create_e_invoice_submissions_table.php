<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Helpers\MigrateUtils;
use Modules\ERP\Casts\EInvoiceSubmissionStatus;
use Modules\ERP\Helpers\ERPMigrateUtils;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('e_invoice_submissions', function (Blueprint $table): void {
            $table->id();
            ERPMigrateUtils::companyForeign($table);
            $table->foreignId('invoice_id')
                ->constrained('invoices', 'id', 'e_invoice_submissions_invoice_id_FK')
                ->restrictOnDelete();
            $table->string('provider_code', 64)->comment('Logical gateway id, e.g. sdi, peppol');
            $table->string('external_id', 191)->nullable()->comment('Id returned by the provider');
            $table->enum('status', array_map(
                static fn (EInvoiceSubmissionStatus $s): string => $s->value,
                EInvoiceSubmissionStatus::cases(),
            ))->default(EInvoiceSubmissionStatus::DRAFT->value);
            $table->text('last_payload_path')->nullable()->comment('Optional storage path for last outbound artifact');
            $table->timestamp('submitted_at')->nullable()->index();
            $table->json('response_payload')->nullable()->comment('Last provider response snapshot');

            MigrateUtils::timestamps(
                $table,
                hasCreateUpdate: true,
                hasSoftDelete: true,
            );

            $table->index(['invoice_id', 'provider_code'], 'e_invoice_submissions_invoice_provider_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('e_invoice_submissions');
    }
};
