<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── Credit Packages ──────────────────────────────────────────────────
        Schema::create('credit_packages', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->unsignedInteger('credits');             // How many instance credits
            $table->decimal('price', 10, 2);
            $table->string('currency', 3)->default('INR');
            $table->unsignedInteger('validity_days')->nullable(); // NULL = no wallet expiry
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // ── Credit Transactions (immutable ledger) ────────────────────────────
        Schema::create('credit_transactions', function (Blueprint $table) {
            $table->id();

            // Who owns this transaction
            $table->unsignedBigInteger('owner_id');
            $table->enum('owner_type', ['user', 'client']); // client for client-admin's wallet

            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();

            $table->enum('type', [
                'purchase',            // Credits bought
                'allocation',          // Credits assigned from wallet to an instance
                'deallocation',        // Credits returned from instance back to wallet
                'consumption',         // Daily accrual deduction
                'refund',              // Credits refunded
                'manual_adjustment',   // Super Admin action
            ]);

            $table->integer('credits');  // Positive = added, Negative = deducted
            $table->foreignId('instance_id')->nullable()->constrained('whatsapp_instances')->nullOnDelete();
            $table->foreignId('package_id')->nullable()->constrained('credit_packages')->nullOnDelete();
            $table->string('reference', 100)->nullable(); // Payment gateway ref or admin note
            $table->integer('balance_after');             // Snapshot of wallet after this tx
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete(); // If admin action

            $table->timestamp('created_at');

            $table->index(['owner_id', 'owner_type']);
            $table->index(['client_id', 'type']);
            $table->index('instance_id');
        });

        // ── Message Rate Limits ───────────────────────────────────────────────
        Schema::create('message_limits', function (Blueprint $table) {
            $table->id();
            // Polymorphic: can be per-user OR per-instance
            $table->unsignedBigInteger('owner_id')->nullable();     // NULL = global default
            $table->enum('owner_type', ['user', 'client', 'instance'])->nullable();
            $table->foreignId('instance_id')->nullable()->constrained('whatsapp_instances')->cascadeOnDelete();
            $table->unsignedSmallInteger('max_per_minute')->default(20);
            $table->timestamps();

            $table->index(['owner_id', 'owner_type']);
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('message_limits');
        Schema::dropIfExists('credit_transactions');
        Schema::dropIfExists('credit_packages');
    }
};