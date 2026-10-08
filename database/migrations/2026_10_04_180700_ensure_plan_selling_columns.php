<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('plans', 'selling_price')) {
            Schema::table('plans', function (Blueprint $table) {
                $table->decimal('selling_price', 12, 2)->nullable()->after('currency');
            });
        }

        if (! Schema::hasColumn('plans', 'selling_currency')) {
            Schema::table('plans', function (Blueprint $table) {
                $table->string('selling_currency', 8)->nullable()->after('selling_price');
            });
        }

        if (! Schema::hasColumn('plans', 'hotspot')) {
            Schema::table('plans', function (Blueprint $table) {
                $table->json('hotspot')->nullable()->after('mikrotik_profile');
            });
        }
    }

    public function down(): void
    {
        foreach (['hotspot', 'selling_currency', 'selling_price'] as $column) {
            if (Schema::hasColumn('plans', $column)) {
                Schema::table('plans', function (Blueprint $table) use ($column) {
                    $table->dropColumn($column);
                });
            }
        }
    }
};
