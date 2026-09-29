<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('slogan')->nullable();
            $table->string('whatsapp', 30)->nullable();
            $table->string('secondary_color', 7)->nullable();
            $table->string('button_color', 7)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('ticket_style', 32)->nullable();
        });

        Schema::table('wifi_zones', function (Blueprint $table) {
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn([
                'slogan', 'whatsapp', 'secondary_color', 'button_color',
                'latitude', 'longitude', 'ticket_style',
            ]);
        });

        Schema::table('wifi_zones', function (Blueprint $table) {
            $table->dropColumn(['latitude', 'longitude']);
        });
    }
};
