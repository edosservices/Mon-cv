<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wifi_zones', function (Blueprint $table) {
            $table->string('display_name')->nullable();
            $table->string('slogan')->nullable();
            $table->string('secondary_color', 7)->nullable();
            $table->string('email')->nullable();
            $table->string('banner_path')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('wifi_zones', function (Blueprint $table) {
            $table->dropColumn(['display_name', 'slogan', 'secondary_color', 'email', 'banner_path']);
        });
    }
};
