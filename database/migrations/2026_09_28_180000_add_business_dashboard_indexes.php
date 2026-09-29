<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->index(['tenant_id', 'status', 'created_at']);
            $table->index(['tenant_id', 'wifi_zone_id', 'created_at']);
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->index(['tenant_id', 'status', 'paid_at']);
        });

        Schema::table('vouchers', function (Blueprint $table) {
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'sync_status']);
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'status', 'created_at']);
            $table->dropIndex(['tenant_id', 'wifi_zone_id', 'created_at']);
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'status', 'paid_at']);
        });

        Schema::table('vouchers', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'status']);
            $table->dropIndex(['tenant_id', 'sync_status']);
        });
    }
};
