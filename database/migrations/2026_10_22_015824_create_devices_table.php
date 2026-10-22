<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('devices', function (Blueprint $table) {
            $table->uuid('device_id')->primary();
            $table->uuid('company_id')->nullable(); // ref lógica Core
            $table->uuid('user_id')->nullable();
            $table->string('device_uuid', 190)->unique();
            $table->string('device_type', 30);
            $table->string('platform', 30);
            $table->timestamp('last_sync_at')->nullable();
            $table->enum('status', ['active', 'inactive', 'revoked'])->default('active');
            $table->timestamps();

            $table->foreign('user_id')
                ->references('user_id')
                ->on('users')
                ->nullOnDelete();

            $table->index('company_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('devices');
    }
};
