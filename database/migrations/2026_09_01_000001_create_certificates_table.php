<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('certificates', function (Blueprint $table) {
            $table->id();

            $table->foreignId('student_id')
                  ->constrained('students')
                  ->cascadeOnDelete();

            $table->foreignId('course_id')
                  ->constrained('courses')
                  ->cascadeOnDelete();

            $table->foreignId('project_id')
                  ->nullable()
                  ->constrained('projects')
                  ->nullOnDelete();

            $table->string('certificate_number')->unique();
            $table->string('certificate_code')->unique()->nullable();
            $table->date('issue_date');
            $table->decimal('score', 5, 2)->nullable();
            $table->string('grade')->nullable();
            $table->string('certificate_file')->nullable();
            $table->string('issued_by')->nullable();
            $table->string('qr_code')->nullable();
            $table->string('verification_url')->nullable();
            $table->json('signatures')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('certificates');
    }
};
