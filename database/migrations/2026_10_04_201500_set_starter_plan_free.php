<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('saas_plans')->where('code', 'starter')->update([
            'price' => 0,
            'currency' => 'USD',
        ]);
    }

    public function down(): void
    {
        DB::table('saas_plans')->where('code', 'starter')->update([
            'price' => null,
            'currency' => 'CDF',
        ]);
    }
};
