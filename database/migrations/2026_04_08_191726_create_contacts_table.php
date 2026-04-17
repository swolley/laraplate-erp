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
        Schema::create('contacts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers', 'id', 'contacts_customer_id_FK')->nullable(true)->setNullOnDelete();
            $table->string('name');
            $table->string('email');
            $table->string('phone');
            $table->string('address');
            $table->string('city');
            $table->string('state');
            $table->string('zip');
            $table->string('country');
            
            MigrateUtils::timestamps(
                $table,
                hasCreateUpdate: true,
                hasSoftDelete: true,
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contacts');
    }
};
