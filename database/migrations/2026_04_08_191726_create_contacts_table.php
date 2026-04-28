<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Business\Helpers\BusinessMigrateUtils;
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
            BusinessMigrateUtils::companyForeign($table);
            $table->unsignedBigInteger('user_id')->nullable(true)->comment('The user that the contact belongs to');
            $table->string('name')->comment('The name of the contact')->nullable(false);
            // not unique because we allow multiple contacts with the same email
            $table->string('email')->comment('The email of the contact')->nullable(true);
            // not unique because we allow multiple contacts with the same phone
            $table->string('phone')->comment('The phone of the contact')->nullable(true);

            MigrateUtils::timestamps(
                $table,
                hasCreateUpdate: true,
                hasSoftDelete: true,
            );

            $table->unique(['user_id', 'name', 'deleted_at'], 'contacts_UN');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
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
