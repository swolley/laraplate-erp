<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Helpers\MigrateUtils;
use Modules\ERP\Enums\ERPTables;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $contactables_table = ERPTables::Contactables->value;
        Schema::create($contactables_table, function (Blueprint $table) use ($contactables_table): void {
            $table->foreignId('party_id')
                ->constrained(ERPTables::Parties->value, 'id', "{$contactables_table}_party_id_FK")
                ->nullable(false)
                ->cascadeOnDelete()
                ->comment('The party that the contact belongs to');
            $table->foreignId('contact_id')
                ->constrained(ERPTables::Contacts->value, 'id', "{$contactables_table}_contact_id_FK")
                ->nullable(false)
                ->cascadeOnDelete()
                ->comment('The contact that the contactable belongs to');

            $table->primary(['party_id', 'contact_id'], "{$contactables_table}_primary_idx")->comment('The primary key of the contactable relationship');

            MigrateUtils::timestamps($table);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(ERPTables::Contactables->value);
    }
};
