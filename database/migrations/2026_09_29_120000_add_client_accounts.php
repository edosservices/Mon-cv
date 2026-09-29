<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable()->change();
            $table->string('client_phone', 20)->nullable()->unique();
        });

        Schema::create('phone_password_resets', function (Blueprint $table) {
            $table->id();
            $table->string('phone', 20)->index();
            $table->string('token');
            $table->timestamp('expires_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('phone_password_resets');

        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['client_phone']);
            $table->dropColumn('client_phone');
            $table->string('email')->nullable(false)->change();
        });
    }
};
