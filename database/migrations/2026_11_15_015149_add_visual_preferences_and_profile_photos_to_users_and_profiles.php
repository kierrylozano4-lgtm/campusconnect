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
        Schema::table('users', function (Blueprint $table) {
            $table->string('design_theme', 32)->default('modern');
            $table->string('design_palette', 32)->default('campus');
        });

        Schema::table('profiles', function (Blueprint $table) {
            $table->string('profile_photo_path')->nullable();
            $table->string('cover_photo_path')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('profiles', function (Blueprint $table) {
            $table->dropColumn(['profile_photo_path', 'cover_photo_path']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['design_theme', 'design_palette']);
        });
    }
};
