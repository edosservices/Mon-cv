<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mikrotiks', function (Blueprint $table) {
            $table->string('identity')->nullable()->after('routeros_version');
        });
    }

    public function down(): void
    {
        Schema::table('mikrotiks', function (Blueprint $table) {
            $table->dropColumn('identity');
        });
    }
};
