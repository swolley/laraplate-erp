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
    public function up(): void
    {
        $pools = ERPTables::PartnerPools->value;
        Schema::create($pools, function (Blueprint $table) use ($pools): void {
            $table->id();
            ERPMigrateUtils::companyForeign($table);
            $table->string('name');
            $table->char('currency', 3);
            $table->boolean('is_active')->default(true);
            MigrateUtils::timestamps($table, hasCreateUpdate: true, hasSoftDelete: true);
            $table->unique(['company_id', 'name'], "{$pools}_company_name_UQ");
        });

        $members = ERPTables::PartnerPoolMembers->value;
        Schema::create($members, function (Blueprint $table) use ($members, $pools): void {
            $table->id();
            $table->foreignId('partner_pool_id')->constrained($pools, 'id', "{$members}_pool_FK")->cascadeOnDelete();
            $table->foreignId('user_id')->constrained(CoreTables::Users->value, 'id', "{$members}_user_FK")->restrictOnDelete();
            MigrateUtils::timestamps($table, hasCreateUpdate: true);
            $table->unique(['partner_pool_id', 'user_id'], "{$members}_pool_user_UQ");
        });

        $allocations = ERPTables::MovementAllocations->value;
        Schema::create($allocations, function (Blueprint $table) use ($allocations, $pools): void {
            $table->id();
            $table->foreignId('partner_pool_id')->constrained($pools, 'id', "{$allocations}_pool_FK")->restrictOnDelete();
            $table->foreignId('movement_id')->constrained(ERPTables::Movements->value, 'id', "{$allocations}_movement_FK")->cascadeOnDelete();
            $table->foreignId('user_id')->constrained(CoreTables::Users->value, 'id', "{$allocations}_user_FK")->restrictOnDelete();
            $table->decimal('owed_amount', 15, 4)->default(0);
            $table->decimal('paid_amount', 15, 4)->default(0);
            MigrateUtils::timestamps($table, hasCreateUpdate: true, hasSoftDelete: true);
            $table->unique(['movement_id', 'user_id'], "{$allocations}_movement_user_UQ");
        });
        ERPMigrateUtils::nonNegativeCheck($allocations, 'ma_owed_nn_ck', 'owed_amount');
        ERPMigrateUtils::nonNegativeCheck($allocations, 'ma_paid_nn_ck', 'paid_amount');

        $transactions = ERPTables::PoolTransactions->value;
        Schema::create($transactions, function (Blueprint $table) use ($transactions, $pools): void {
            $table->id();
            $table->foreignId('partner_pool_id')->constrained($pools, 'id', "{$transactions}_pool_FK")->restrictOnDelete();
            $table->foreignId('from_user_id')->constrained(CoreTables::Users->value, 'id', "{$transactions}_from_user_FK")->restrictOnDelete();
            $table->foreignId('to_user_id')->constrained(CoreTables::Users->value, 'id', "{$transactions}_to_user_FK")->restrictOnDelete();
            $table->decimal('amount', 15, 4);
            $table->char('currency', 3);
            $table->date('occurred_on');
            $table->timestamp('confirmed_at');
            $table->text('description')->nullable();
            MigrateUtils::timestamps($table, hasCreateUpdate: true, hasSoftDelete: true);
            $table->index(['partner_pool_id', 'occurred_on'], "{$transactions}_pool_date_IDX");
        });
        ERPMigrateUtils::positiveCheck($transactions, 'pt_amount_pos_ck', 'amount');
    }

    public function down(): void
    {
        Schema::dropIfExists(ERPTables::PoolTransactions->value);
        Schema::dropIfExists(ERPTables::MovementAllocations->value);
        Schema::dropIfExists(ERPTables::PartnerPoolMembers->value);
        Schema::dropIfExists(ERPTables::PartnerPools->value);
    }
};
