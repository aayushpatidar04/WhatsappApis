<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('instance_id')->constrained('whatsapp_instances')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->foreignId('campaign_id')->nullable()->constrained('campaigns')->nullOnDelete();

            // Direction
            $table->enum('direction', ['inbound', 'outbound'])->index();

            // Message identity
            $table->string('wa_message_id', 100)->nullable()->index(); // WA-assigned ID for ACK matching
            $table->string('recipient_jid', 60)->index();              // phone@s.whatsapp.net or group@g.us

            // Content
            $table->enum('type', [
                'text', 'image', 'video', 'audio', 'document',
                'location', 'contact', 'sticker', 'reaction', 'poll',
                'interactive', 'template', 'unknown',
            ])->default('text');
            $table->text('body')->nullable();                  // text body or caption
            $table->string('media_url', 500)->nullable();      // stored media path
            $table->string('media_mime', 80)->nullable();
            $table->string('media_filename', 255)->nullable();
            $table->json('metadata')->nullable();              // raw extra data (location coords, poll options, etc.)

            // Status tracking
            $table->enum('status', [
                'queued', 'sending', 'sent', 'delivered', 'read', 'failed', 'rejected',
            ])->default('queued')->index();
            $table->text('error_message')->nullable();
            $table->unsignedTinyInteger('retry_count')->default(0);

            // Timestamps
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['instance_id', 'direction', 'created_at']);
            $table->index(['client_id', 'status', 'created_at']);
            $table->index(['wa_message_id', 'instance_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};