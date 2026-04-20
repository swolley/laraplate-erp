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
        Schema::create('price_lists', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->char('currency', 3)->default('EUR')->comment('ISO 4217; line items inherit this currency');

            MigrateUtils::timestamps(
                $table,
                hasCreateUpdate: true,
                hasSoftDelete: true,
                hasLocks: false,
                hasValidity: true,
                isValidityRequired: false,
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('price_lists');
    }
};
