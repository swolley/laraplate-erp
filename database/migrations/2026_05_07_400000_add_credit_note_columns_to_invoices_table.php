<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->string('invoice_type', 32)->default('invoice')->after('direction');
            $table->foreignId('credited_invoice_id')->nullable()->after('invoice_type')
                ->constrained('invoices', 'id', 'invoices_credited_invoice_FK')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropForeign('invoices_credited_invoice_FK');
            $table->dropColumn(['credited_invoice_id', 'invoice_type']);
        });
    }
};
