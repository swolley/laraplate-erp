<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Helpers\MigrateUtils;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table): void {
            $table->id();
            $table->string('slug')->unique()->comment('Stable machine-friendly identifier (used in URLs, configs, etc.)');
            $table->string('name')->comment('Human-friendly display name');
            $table->string('legal_name')->nullable()->comment('Registered legal name; falls back to name when null');
            $table->string('tax_id')->nullable()->index()->comment('Italian P.IVA / EU VAT number / equivalent fiscal id');
            $table->string('fiscal_country', 2)->default('IT')->comment('ISO 3166-1 alpha-2 country code driving fiscal rules');
            $table->char('default_currency', 3)->default('EUR')->comment('ISO 4217 functional currency used as amount_local for journal balancing');
            $table->json('settings')->nullable()->comment('Free-form per-company settings (logo, defaults, etc.)');
            $table->boolean('is_default')->default(false)->index()->comment('At most one company per environment can be the default tenant');

            MigrateUtils::timestamps(
                $table,
                hasCreateUpdate: true,
                hasSoftDelete: true,
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
