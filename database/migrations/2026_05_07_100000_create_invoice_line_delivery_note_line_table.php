<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_line_delivery_note_line', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('invoice_line_id')
                ->constrained('invoice_lines', 'id', 'ildnl_invoice_line_id_FK')
                ->cascadeOnDelete();
            $table->foreignId('delivery_note_line_id')
                ->constrained('delivery_note_lines', 'id', 'ildnl_delivery_note_line_id_FK')
                ->restrictOnDelete();
            $table->unsignedInteger('quantity')->comment('Qty of this DDT line covered by this invoice line');

            $table->unique(
                ['invoice_line_id', 'delivery_note_line_id'],
                'ildnl_invoice_dn_UN',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_line_delivery_note_line');
    }
};
