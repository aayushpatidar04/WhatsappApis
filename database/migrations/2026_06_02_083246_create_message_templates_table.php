<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('message_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->string('name', 100);
            $table->enum('category', ['text', 'image', 'video', 'document', 'poll']);
            $table->text('body');
            $table->string('media_url')->nullable();
            $table->string('media_type')->nullable(); // image/jpeg, video/mp4, etc.
            $table->json('variables')->nullable(); // ['name', 'phone', 'code']
            $table->text('description')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['client_id', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_templates');
    }
};