<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Enums\CoreTables;
use Modules\Core\Helpers\MigrateUtils;
use Modules\ERP\Casts\LeadStatus;
use Modules\ERP\Enums\ERPTables;
use Modules\ERP\Helpers\ERPMigrateUtils;

return new class extends Migration
{
    public function up(): void
    {
        $leads_table = ERPTables::Leads->value;
        Schema::create($leads_table, function (Blueprint $table) use ($leads_table): void {
            $table->id();
            ERPMigrateUtils::companyForeign($table);
            $table->foreignId('party_id')
                ->nullable()
                ->constrained(ERPTables::Parties->value, 'id', "{$leads_table}_party_id_FK")
                ->nullOnDelete()
                ->comment('Linked party once qualified (optional on cold leads)');
            $table->foreignId('contact_id')
                ->nullable()
                ->constrained(ERPTables::Contacts->value, 'id', "{$leads_table}_contact_id_FK")
                ->nullOnDelete()
                ->comment('Primary contact person when known');
            $table->string('title')->comment('Short label for the lead');
            $table->string('source', 128)->nullable()->comment('e.g. web, referral, partner');
            $table->enum('status', array_map(
                static fn (LeadStatus $s): string => $s->value,
                LeadStatus::cases(),
            ))->default(LeadStatus::New->value)->index("{$leads_table}_status_IDX");
            $table->foreignId('owner_user_id')
                ->nullable()
                ->constrained(CoreTables::Users->value, 'id', "{$leads_table}_owner_user_id_FK")
                ->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamp('converted_at')->nullable()->comment('When the lead moved to an opportunity or party flow');

            MigrateUtils::timestamps(
                $table,
                hasCreateUpdate: true,
                hasSoftDelete: true,
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(ERPTables::Leads->value);
    }
};
