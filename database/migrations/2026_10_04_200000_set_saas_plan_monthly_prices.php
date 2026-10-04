<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('saas_plans')->where('code', 'business')->update([
            'price' => 4.90,
            'currency' => 'USD',
        ]);

        DB::table('saas_plans')->where('code', 'pro')->update([
            'price' => 8.90,
            'currency' => 'USD',
        ]);
    }

    public function down(): void
    {
        DB::table('saas_plans')->whereIn('code', ['business', 'pro'])->update([
            'price' => null,
            'currency' => 'CDF',
        ]);
    }
};
