<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('captive_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('wifi_zone_id')->constrained()->cascadeOnDelete();
            $table->foreignId('mikrotik_id')->nullable()->constrained()->nullOnDelete();
            $table->string('mac_address', 32);
            $table->string('ip_address', 45);
            $table->string('hotspot_username')->nullable();
            $table->string('hotspot_server')->nullable();
            $table->timestamp('verified_at');
            $table->timestamps();
            $table->index(['wifi_zone_id', 'mac_address']);
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->string('purchase_for', 16)->default('self')->after('channel');
            $table->string('payer_phone')->nullable()->after('purchase_for');
            $table->string('beneficiary_phone')->nullable()->after('payer_phone');
            $table->foreignId('captive_session_id')->nullable()->after('beneficiary_phone')->constrained()->nullOnDelete();
        });

        Schema::table('vouchers', function (Blueprint $table) {
            $table->string('mac_address', 32)->nullable()->after('customer_id');
        });
    }

    public function down(): void
    {
        Schema::table('vouchers', function (Blueprint $table) {
            $table->dropColumn('mac_address');
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->dropConstrainedForeignId('captive_session_id');
            $table->dropColumn(['purchase_for', 'payer_phone', 'beneficiary_phone']);
        });

        Schema::dropIfExists('captive_sessions');
    }
};
