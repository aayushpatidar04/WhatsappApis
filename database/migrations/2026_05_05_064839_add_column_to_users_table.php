<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('client_id')->nullable()->constrained('clients')->nullOnDelete();
            $table->enum('role', ['super_admin', 'client_admin', 'user'])->default('user');
            $table->unsignedInteger('credit_balance')->default(0); // User-level instance credit wallet
            $table->string('phone')->nullable();
            $table->string('timezone')->default('Asia/Kolkata');
            $table->boolean('is_active')->default(true);
            $table->softDeletes();
 
            $table->index(['client_id', 'role']);
            $table->index(['email', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            //
        });
    }
};
