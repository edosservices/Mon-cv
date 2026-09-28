<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $exists = DB::table('payment_provider_settings')->where('provider', 'unipay')->exists();
        if ($exists) {
            return;
        }

        $now = now();
        DB::table('payment_provider_settings')->insert([
            'provider' => 'unipay',
            'enabled' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        DB::table('payment_provider_settings')->where('provider', 'unipay')->delete();
    }
};
