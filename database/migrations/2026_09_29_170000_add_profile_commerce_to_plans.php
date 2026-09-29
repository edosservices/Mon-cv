<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->decimal('selling_price', 12, 2)->nullable()->after('currency');
            $table->string('selling_currency', 8)->nullable()->after('selling_price');
            $table->json('hotspot')->nullable()->after('mikrotik_profile');
        });

        Schema::table('vouchers', function (Blueprint $table) {
            if (! Schema::hasIndex('vouchers', ['tenant_id', 'created_at'])) {
                $table->index(['tenant_id', 'created_at']);
            }
        });
    }

    public function down(): void
    {
        Schema::table('vouchers', function (Blueprint $table) {
            if (Schema::hasIndex('vouchers', ['tenant_id', 'created_at'])) {
                $table->dropIndex(['tenant_id', 'created_at']);
            }
        });

        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn(['selling_price', 'selling_currency', 'hotspot']);
        });
    }
};
