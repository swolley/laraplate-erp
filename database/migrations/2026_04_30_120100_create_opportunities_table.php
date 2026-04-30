<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Helpers\MigrateUtils;
use Modules\ERP\Casts\OpportunityStatus;
use Modules\ERP\Helpers\ERPMigrateUtils;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('opportunities', function (Blueprint $table): void {
            $table->id();
            ERPMigrateUtils::companyForeign($table);
            $table->foreignId('lead_id')
                ->nullable()
                ->constrained('leads', 'id', 'opportunities_lead_id_FK')
                ->nullOnDelete();
            $table->foreignId('customer_id')
                ->nullable(false)
                ->constrained('customers', 'id', 'opportunities_customer_id_FK')
                ->restrictOnDelete();
            $table->foreignId('stage_taxonomy_id')
                ->nullable(false)
                ->constrained('taxonomies', 'id', 'opportunities_stage_taxonomy_id_FK')
                ->restrictOnDelete()
                ->comment('Pipeline stage; use EntityType::OPPORTUNITY_STAGES tree');
            $table->string('name')->comment('Opportunity title');
            $table->enum('status', array_map(
                static fn (OpportunityStatus $s): string => $s->value,
                OpportunityStatus::cases(),
            ))->default(OpportunityStatus::OPEN->value)->index('opportunities_status_IDX');
            $table->date('expected_close_date')->nullable();
            $table->decimal('expected_value_doc', 15, 4)->nullable()->comment('Weighted expected value in document currency');
            $table->char('expected_currency_doc', 3)->default('EUR');
            $table->decimal('expected_value_local', 15, 4)->nullable()->comment('Expected value in company functional currency');
            $table->char('expected_currency_local', 3)->default('EUR');
            $table->decimal('expected_fx_rate', 18, 8)->default(1);
            $table->unsignedTinyInteger('probability')->nullable()->comment('Win probability 0–100');
            $table->timestamp('won_at')->nullable();
            $table->timestamp('lost_at')->nullable();
            $table->text('lost_reason')->nullable();

            MigrateUtils::timestamps(
                $table,
                hasCreateUpdate: true,
                hasSoftDelete: true,
            );

            $table->index(['company_id', 'customer_id'], 'opportunities_company_customer_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('opportunities');
    }
};
