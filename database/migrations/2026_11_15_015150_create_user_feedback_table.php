<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('user_feedback', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('sender_name', 120);
            $table->string('sender_email');
            $table->string('recipient', 20)->index();
            $table->string('subject', 150);
            $table->text('message');
            $table->string('status', 20)->default('New');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'read_at']);
            $table->index(['recipient', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_feedback');
    }
};
