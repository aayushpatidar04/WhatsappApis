<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── Campaigns ─────────────────────────────────────────────────────────
        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('instance_id')->nullable()->constrained('whatsapp_instances')->nullOnDelete();
            $table->foreignId('contact_group_id')->nullable()->constrained('contact_groups')->nullOnDelete();

            $table->string('name', 255);
            $table->enum('status', [
                'draft', 'scheduled', 'running', 'paused', 'completed', 'failed', 'cancelled',
            ])->default('draft');

            // Message payload
            $table->enum('message_type', [
                'text', 'image', 'video', 'audio', 'document', 'location', 'poll', 'template',
            ])->default('text');
            $table->json('message_payload');        // full message data with variables

            // Schedule
            $table->timestamp('schedule_time')->nullable();     // when to start
            $table->time('send_window_start')->nullable();      // e.g. 09:00
            $table->time('send_window_end')->nullable();        // e.g. 21:00

            // Stats (denormalised for quick reads)
            $table->unsignedInteger('total_recipients')->default(0);
            $table->unsignedInteger('sent_count')->default(0);
            $table->unsignedInteger('delivered_count')->default(0);
            $table->unsignedInteger('read_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);

            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['client_id', 'status']);
            $table->index('schedule_time');
        });

        // ── Campaign Recipients ───────────────────────────────────────────────
        Schema::create('campaign_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained('campaigns')->cascadeOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained('contacts')->nullOnDelete();
            $table->foreignId('message_id')->nullable()->constrained('messages')->nullOnDelete();
            $table->string('phone', 20);
            $table->string('name', 255)->nullable();
            $table->json('variables')->nullable();          // resolved template variables
            $table->enum('status', [
                'pending', 'queued', 'sent', 'delivered', 'read', 'failed', 'skipped',
            ])->default('pending');
            $table->text('error_message')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['campaign_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_recipients');
        Schema::dropIfExists('campaigns');
    }
};