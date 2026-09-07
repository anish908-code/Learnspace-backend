<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('profile_image', 2048)->nullable()->change();
        });

        Schema::table('students', function (Blueprint $table) {
            $table->string('profile_image', 2048)->nullable()->change();
        });

        Schema::table('courses', function (Blueprint $table) {
            $table->string('thumbnail', 2048)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('profile_image', 255)->nullable()->change();
        });

        Schema::table('students', function (Blueprint $table) {
            $table->string('profile_image', 255)->nullable()->change();
        });

        Schema::table('courses', function (Blueprint $table) {
            $table->string('thumbnail', 255)->nullable()->change();
        });
    }
};
