<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── Contacts ──────────────────────────────────────────────────────────
        Schema::create('contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('name', 255);
            $table->string('phone', 20);                    // E.164 format
            $table->string('email', 255)->nullable();
            $table->json('tags')->nullable();               // ['VIP', 'Lead']
            $table->json('custom_fields')->nullable();      // { "company": "Acme" }
            $table->boolean('is_whatsapp')->nullable();     // verified WA number
            $table->boolean('is_blocked')->default(false);  // opt-out / blocked
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['client_id', 'phone']);
            $table->index(['client_id', 'is_blocked']);
            $table->index('phone');
        });

        // ── Contact Groups ────────────────────────────────────────────────────
        Schema::create('contact_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->timestamps();
        });

        // ── Group Members (pivot) ─────────────────────────────────────────────
        Schema::create('contact_group_members', function (Blueprint $table) {
            $table->foreignId('group_id')->constrained('contact_groups')->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained('contacts')->cascadeOnDelete();
            $table->primary(['group_id', 'contact_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_group_members');
        Schema::dropIfExists('contact_groups');
        Schema::dropIfExists('contacts');
    }
};