<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();                    // URL-safe tenant identifier
            $table->unsignedBigInteger('super_admin_id')->nullable(); // set after super admin created
            $table->unsignedInteger('max_rate_per_minute')->default(20);
            $table->unsignedInteger('max_instances_per_user')->default(5);
            $table->unsignedInteger('credit_balance')->default(0); // Client-level wallet (for client-owned instances)
            $table->json('settings')->nullable();                // branding, timezone, etc.
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clients');
    }
};