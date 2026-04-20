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
            $table->unsignedBigInteger('user_id')->nullable(true)->comment('The user that the contact belongs to');
            $table->string('name')->unique('contacts_name_UN');
            // not unique because we allow multiple contacts with the same email
            $table->string('email')->nullable();
            // not unique because we allow multiple contacts with the same phone
            $table->string('phone')->nullable();

            MigrateUtils::timestamps(
                $table,
                hasCreateUpdate: true,
                hasSoftDelete: true,
            );

            $table->unique(['user_id', 'name', 'deleted_at'], 'contacts_UN');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
        });

        Schema::create('customers_contacts', function (Blueprint $table): void {
            $table->foreignId('customer_id')->constrained('customers', 'id', 'customers_contacts_customer_id_FK')->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained('contacts', 'id', 'customers_contacts_contact_id_FK')->cascadeOnDelete();
            $table->primary(['customer_id', 'contact_id']);

            MigrateUtils::timestamps($table);
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
