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
        Schema::table('conversation_messages', function (Blueprint $table) {
            $table->string('activity_key', 160)->nullable()->after('type');
            $table->unique('activity_key', 'conversation_activity_key_unique');
        });
    }

    public function down(): void
    {
        Schema::table('conversation_messages', function (Blueprint $table) {
            $table->dropUnique('conversation_activity_key_unique');
            $table->dropColumn('activity_key');
        });
    }
};
