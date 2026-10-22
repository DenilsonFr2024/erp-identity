<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_roles', function (Blueprint $table) {
            $table->uuid('user_role_id')->primary();
            $table->uuid('user_id');
            $table->uuid('role_id');
            // company_id e branch_id: referência lógica ao Core (sem FK física)
            $table->uuid('company_id')->nullable();
            $table->uuid('branch_id')->nullable();
            $table->timestamps();

            $table->foreign('user_id')
                ->references('user_id')
                ->on('users')
                ->cascadeOnDelete();
            $table->foreign('role_id')
                ->references('role_id')
                ->on('roles')
                ->cascadeOnDelete();

            $table->unique(['user_id', 'role_id', 'company_id', 'branch_id'], 'user_role_unique');
            $table->index('company_id');
            $table->index('branch_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_roles');
    }
};
