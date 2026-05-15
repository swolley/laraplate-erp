<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Enums\CoreTables;
use Modules\Core\Helpers\MigrateUtils;
use Modules\ERP\Enums\ERPTables;
use Modules\ERP\Helpers\ERPMigrateUtils;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $contacts_table = ERPTables::Contacts->value;
        Schema::create($contacts_table, function (Blueprint $table) use ($contacts_table): void {
            $table->id();
            ERPMigrateUtils::companyForeign($table);
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

            $table->unique(['user_id', 'name', 'deleted_at'], "{$contacts_table}_UN");
            $table->foreign('user_id', "{$contacts_table}_user_id_FK")->references('id')->on(CoreTables::Users->value)->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(ERPTables::Contacts->value);
    }
};
