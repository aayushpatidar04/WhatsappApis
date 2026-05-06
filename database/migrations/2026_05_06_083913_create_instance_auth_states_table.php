<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Stores the Baileys multi-file auth state per instance.
     * The Node.js service reads/writes via internal API endpoints.
     * Data is encrypted before storage using APP_KEY-derived encryption.
     */
    public function up(): void
    {
        Schema::create('instance_auth_states', function (Blueprint $table) {
            $table->id();
            $table->foreignId('instance_id')->unique()->constrained('whatsapp_instances')->cascadeOnDelete();
            $table->string('instance_token', 80)->unique();   // Denormalised for fast lookup
            $table->longText('session_data');                 // Encrypted JSON of all auth files
            $table->timestamp('last_synced_at')->nullable();  // When Baileys last pushed new creds
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('instance_auth_states');
    }
};