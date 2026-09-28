<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mikrotiks', function (Blueprint $table) {
            $table->text('description')->nullable()->after('name');
            $table->string('dns')->nullable()->after('host');
            $table->unsignedInteger('api_ssl_port')->default(8729)->after('api_port');
            $table->string('connection_type', 16)->default('api')->after('api_ssl_port');
            $table->unsignedSmallInteger('timeout')->default(5)->after('username');
            $table->string('architecture')->nullable()->after('routeros_version');
            $table->string('board')->nullable()->after('architecture');
            $table->string('hotspot_server')->nullable()->after('identity');
            $table->boolean('is_active')->default(true)->after('status');
        });

        Schema::create('plan_mikrotik_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('mikrotik_id')->constrained()->cascadeOnDelete();
            $table->foreignId('mikrotik_profile_id')->nullable()->constrained('mikrotik_profiles')->nullOnDelete();
            $table->timestamps();
            $table->unique(['plan_id', 'mikrotik_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_mikrotik_profiles');

        Schema::table('mikrotiks', function (Blueprint $table) {
            $table->dropColumn([
                'description', 'dns', 'api_ssl_port', 'connection_type', 'timeout',
                'architecture', 'board', 'hotspot_server', 'is_active',
            ]);
        });
    }
};
