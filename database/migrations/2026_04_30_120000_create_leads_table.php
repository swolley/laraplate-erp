<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Helpers\MigrateUtils;
use Modules\ERP\Casts\LeadStatus;
use Modules\ERP\Helpers\ERPMigrateUtils;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leads', function (Blueprint $table): void {
            $table->id();
            ERPMigrateUtils::companyForeign($table);
            $table->foreignId('customer_id')
                ->nullable()
                ->constrained('customers', 'id', 'leads_customer_id_FK')
                ->nullOnDelete()
                ->comment('Linked customer once qualified (optional on cold leads)');
            $table->foreignId('contact_id')
                ->nullable()
                ->constrained('contacts', 'id', 'leads_contact_id_FK')
                ->nullOnDelete()
                ->comment('Primary contact person when known');
            $table->string('title')->comment('Short label for the lead');
            $table->string('source', 128)->nullable()->comment('e.g. web, referral, partner');
            $table->enum('status', array_map(
                static fn (LeadStatus $s): string => $s->value,
                LeadStatus::cases(),
            ))->default(LeadStatus::NEW->value)->index('leads_status_IDX');
            $table->foreignId('owner_user_id')
                ->nullable()
                ->constrained('users', 'id', 'leads_owner_user_id_FK')
                ->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamp('converted_at')->nullable()->comment('When the lead moved to an opportunity or customer flow');

            MigrateUtils::timestamps(
                $table,
                hasCreateUpdate: true,
                hasSoftDelete: true,
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};
