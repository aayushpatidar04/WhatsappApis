<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Webhook endpoints registered by users
        Schema::create('webhooks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->foreignId('instance_id')->nullable()->constrained('whatsapp_instances')->cascadeOnDelete();
            // null instance_id = applies to ALL user's instances

            $table->string('name', 100);
            $table->string('url', 500);
            $table->string('secret', 64);              // HMAC-SHA256 signing secret
            $table->json('events');                    // ['message.inbound', 'message.ack', ...]
            $table->boolean('is_active')->default(true);
            $table->unsignedTinyInteger('failure_count')->default(0);
            $table->timestamp('last_triggered_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'is_active']);
            $table->index(['instance_id', 'is_active']);
        });

        // Delivery log per webhook attempt
        Schema::create('webhook_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('webhook_id')->constrained('webhooks')->cascadeOnDelete();
            $table->foreignId('message_id')->nullable()->constrained('messages')->nullOnDelete();
            $table->string('event', 80);
            $table->json('payload');                   // what we sent
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->text('response_body')->nullable();
            $table->unsignedSmallInteger('attempt')->default(1);
            $table->boolean('success')->default(false);
            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestamp('created_at');

            $table->index(['webhook_id', 'success']);
            $table->index(['webhook_id', 'created_at']);
        });

        // Rate limit tracking per user/instance
        Schema::create('rate_limit_buckets', function (Blueprint $table) {
            $table->string('key', 120)->primary();    // "instance:{id}" or "user:{id}"
            $table->unsignedSmallInteger('tokens');   // remaining tokens in the minute window
            $table->timestamp('window_start');        // when this minute window began
            $table->timestamp('updated_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rate_limit_buckets');
        Schema::dropIfExists('webhook_logs');
        Schema::dropIfExists('webhooks');
    }
};