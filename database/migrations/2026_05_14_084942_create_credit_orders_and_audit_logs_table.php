<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credit_orders', function (Blueprint $table) {
            $table->id();

            // Who is purchasing
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();   // client admin who triggered
            $table->foreignId('package_id')->constrained('credit_packages')->cascadeOnDelete();

            // Order details
            $table->string('order_number', 40)->unique();   // WAP-2024-00001
            $table->unsignedInteger('credits');
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('INR');

            // Payment gateway
            $table->enum('gateway', ['razorpay', 'stripe', 'manual'])->default('razorpay');
            $table->string('gateway_order_id', 100)->nullable();     // Razorpay order_id / Stripe payment_intent
            $table->string('gateway_payment_id', 100)->nullable();   // Payment confirmation ID
            $table->string('gateway_signature', 255)->nullable();    // Razorpay signature for verification

            // Status
            $table->enum('status', ['pending', 'paid', 'failed', 'refunded'])->default('pending');
            $table->text('failure_reason')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['client_id', 'status']);
            $table->index('gateway_order_id');
        });

        // Audit log table
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event', 100);                    // e.g. client.created, credit.adjusted
            $table->string('auditable_type', 80)->nullable();
            $table->unsignedBigInteger('auditable_id')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamp('created_at');

            $table->index(['user_id', 'event']);
            $table->index('created_at');
            $table->index(['auditable_type', 'auditable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('credit_orders');
    }
};