<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Helpers\MigrateUtils;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('contactables', function (Blueprint $table): void {
            $table->foreignId('customer_id')
                ->constrained('customers', 'id', 'contactables_customer_id_FK')
                ->nullable(false)
                ->cascadeOnDelete()
                ->comment('The customer that the contact belongs to');
            $table->foreignId('contact_id')
                ->constrained('contacts', 'id', 'contactables_contact_id_FK')
                ->nullable(false)
                ->cascadeOnDelete()
                ->comment('The contact that the contactable belongs to');

            $table->primary(['customer_id', 'contact_id'])->comment('The primary key of the contactable relationship');

            MigrateUtils::timestamps($table);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contactables');
    }
};
