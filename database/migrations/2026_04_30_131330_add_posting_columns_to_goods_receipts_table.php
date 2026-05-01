<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('goods_receipts', function (Blueprint $table): void {
            $table->timestamp('posted_at')->nullable()->after('received_at');
            $table->timestamp('inventory_posted_at')->nullable()->after('posted_at');
        });
    }

    public function down(): void
    {
        Schema::table('goods_receipts', function (Blueprint $table): void {
            $table->dropColumn(['posted_at', 'inventory_posted_at']);
        });
    }
};
