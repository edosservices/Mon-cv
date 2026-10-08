<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->text('ikeepay_public_key')->nullable()->after('custom_domain');
            $table->text('ikeepay_secret_key')->nullable()->after('ikeepay_public_key');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->string('payer_phone')->nullable()->after('currency');
            $table->string('beneficiary_phone')->nullable()->after('payer_phone');
        });

        Schema::table('vouchers', function (Blueprint $table) {
            $table->string('payer_phone')->nullable()->after('mac_address');
            $table->string('beneficiary_phone')->nullable()->after('payer_phone');
        });
    }

    public function down(): void
    {
        Schema::table('vouchers', function (Blueprint $table) {
            $table->dropColumn(['payer_phone', 'beneficiary_phone']);
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn(['payer_phone', 'beneficiary_phone']);
        });

        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['ikeepay_public_key', 'ikeepay_secret_key']);
        });
    }
};
