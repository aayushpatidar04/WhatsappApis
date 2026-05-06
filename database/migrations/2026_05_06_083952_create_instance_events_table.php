<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Audit log for all session lifecycle events coming from Baileys.
     * Useful for debugging, monitoring, and showing history in the dashboard.
     */
    public function up(): void
    {
        Schema::create('instance_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('instance_id')->constrained('whatsapp_instances')->cascadeOnDelete();
            $table->string('event', 80);           // e.g. session.connected, message.inbound
            $table->json('payload')->nullable();   // Raw event data from Baileys
            $table->timestamp('occurred_at');

            $table->index(['instance_id', 'event']);
            $table->index('occurred_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('instance_events');
    }
};