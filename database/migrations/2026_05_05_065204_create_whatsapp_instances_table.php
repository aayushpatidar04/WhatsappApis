<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The whatsapp_instances table uses a polymorphic owner pattern:
     *
     *   owner_type = 'user'        → owner_id references users.id   (regular end user)
     *   owner_type = 'client'      → owner_id references clients.id  (master admin direct ownership)
     *   owner_type = 'super_admin' → owner_id references users.id   (platform admin)
     *
     * Every instance gets a unique instance_token used as the routing key
     * in the Baileys Node.js service and in API calls via X-Instance-Token header.
     */
    public function up(): void
    {
        Schema::create('whatsapp_instances', function (Blueprint $table) {
            $table->id();

            // Polymorphic owner
            $table->unsignedBigInteger('owner_id');
            $table->enum('owner_type', ['user', 'client', 'super_admin']);

            // Tenant scope (always set, even for client/super_admin owned instances)
            $table->foreignId('client_id')->nullable()->constrained('clients')->nullOnDelete();

            // Instance identity
            $table->string('name', 100);                         // User-assigned label e.g. "Sales WA"
            $table->string('phone_number', 20)->nullable();      // Populated after QR scan

            // Routing key — THE primary identifier for Baileys session lookup
            $table->string('instance_token', 80)->unique();      // bin2hex(random_bytes(40))

            // Status machine
            $table->enum('status', [
                'pending',       // Created, awaiting QR scan
                'active',        // QR scanned, session live
                'disconnected',  // WA dropped connection (temporary)
                'suspended',     // Credits exhausted or manually paused
                'expired',       // Grace period passed, session purged
            ])->default('pending');

            // Credit tracking
            $table->unsignedInteger('credits_assigned')->default(0);
            $table->decimal('credits_consumed', 8, 4)->default(0.0000); // Accrues daily (1/30 per day)

            // Timestamps
            $table->timestamp('activated_at')->nullable();       // First successful QR scan
            $table->timestamp('expires_at')->nullable();         // activated_at + (credits_assigned * 30 days)
            $table->timestamp('suspended_at')->nullable();
            $table->timestamp('last_connected_at')->nullable();  // Updated on each successful reconnect

            // Session data (encrypted Baileys auth state JSON)
            $table->longText('session_data')->nullable();

            // Reconnect tracking
            $table->unsignedTinyInteger('reconnect_attempts')->default(0);

            // Webhook for this instance (quick-access; full webhook table in Phase 3)
            $table->string('webhook_url', 500)->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['owner_id', 'owner_type']);
            $table->index(['client_id', 'status']);
            $table->index('instance_token');
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_instances');
    }
};