<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * instance_credits — per-instance credit ledger.
 *
 * Credit model rules:
 *   - 1 credit  = 1 calendar month of instance access
 *   - starts_at = when this credit period begins
 *   - expires_at = starts_at + 1 month (exactly)
 *   - Credits chain: if an active credit exists, new credits queue up
 *     and start automatically when the previous one expires.
 *   - The cron job `credits:activate-queued` runs hourly to activate
 *     queued credits whose starts_at <= now().
 *   - The cron job `instances:expiry-check` runs daily to suspend
 *     instances whose last active credit has expired.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('instance_credits', function (Blueprint $table) {
            $table->id();

            $table->foreignId('instance_id')
                ->constrained('whatsapp_instances')
                ->cascadeOnDelete();

            // Wallet owner (who paid for this credit)
            $table->unsignedBigInteger('owner_id');
            $table->enum('owner_type', ['user', 'client']);
            $table->foreignId('client_id')->nullable()->constrained('clients')->nullOnDelete();

            // Optional link back to the payment order
            $table->foreignId('credit_order_id')
                ->nullable()
                ->constrained('credit_orders')
                ->nullOnDelete();

            // Status lifecycle: queued → active → expired  (or cancelled)
            $table->enum('status', ['queued', 'active', 'expired', 'cancelled'])
                ->default('queued')
                ->index();

            // The credit window
            $table->timestamp('starts_at');            // When this month begins
            $table->timestamp('expires_at');            // starts_at + 1 month

            // Audit timestamps
            $table->timestamp('activated_at')->nullable();  // Set by cron when status → active
            $table->timestamp('expired_at')->nullable();    // Set by cron when status → expired

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Indexes for the two cron queries
            $table->index(['instance_id', 'status']);
            $table->index('starts_at');
            $table->index('expires_at');
            $table->index(['owner_id', 'owner_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('instance_credits');
    }
};