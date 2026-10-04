<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->addPlanColumn('selling_price', function (Blueprint $table) {
            $table->decimal('selling_price', 12, 2)->nullable()->after('currency');
        });
        $this->addPlanColumn('selling_currency', function (Blueprint $table) {
            $table->string('selling_currency', 8)->nullable()->after('selling_price');
        });
        $this->addPlanColumn('hotspot', function (Blueprint $table) {
            $table->json('hotspot')->nullable()->after('mikrotik_profile');
        });

        if (! Schema::hasIndex('vouchers', ['tenant_id', 'created_at'])) {
            Schema::table('vouchers', function (Blueprint $table) {
                $table->index(['tenant_id', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasIndex('vouchers', ['tenant_id', 'created_at'])) {
            Schema::table('vouchers', function (Blueprint $table) {
                $table->dropIndex(['tenant_id', 'created_at']);
            });
        }

        foreach (['hotspot', 'selling_currency', 'selling_price'] as $column) {
            if (Schema::hasColumn('plans', $column)) {
                Schema::table('plans', function (Blueprint $table) use ($column) {
                    $table->dropColumn($column);
                });
            }
        }
    }

    private function addPlanColumn(string $column, callable $definition): void
    {
        if (Schema::hasColumn('plans', $column)) {
            return;
        }

        Schema::table('plans', $definition);
    }
};
