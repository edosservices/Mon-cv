<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('internal_reference')->nullable()->unique()->after('provider');
            $table->string('provider_reference')->nullable()->index()->after('transaction_reference');
        });

        Schema::create('payment_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('payment_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->json('payload')->nullable();
            $table->timestamps();
        });

        Schema::create('payment_provider_settings', function (Blueprint $table) {
            $table->id();
            $table->string('provider')->unique();
            $table->boolean('enabled')->default(true);
            $table->timestamps();
        });

        $now = now();
        foreach (array_keys(config('limete.payment_providers')) as $provider) {
            DB::table('payment_provider_settings')->insert([
                'provider' => $provider,
                'enabled' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_provider_settings');
        Schema::dropIfExists('payment_events');
        Schema::table('payments', function (Blueprint $table) {
            $table->dropUnique(['internal_reference']);
            $table->dropIndex(['provider_reference']);
            $table->dropColumn(['internal_reference', 'provider_reference']);
        });
    }
};
