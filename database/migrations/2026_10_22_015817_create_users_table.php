<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->uuid('user_id')->primary();
            $table->string('username', 80)->unique();
            $table->string('email', 190)->unique();
            $table->string('password', 255);
            $table->enum('account_type', ['internal', 'partner', 'client']);
            $table->enum('status', ['active', 'pending_approval', 'suspended', 'inactive'])
                ->default('pending_approval');
            $table->timestamps();
            $table->rememberToken();
            $table->index('account_type');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
