<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('internship_rosters', function (Blueprint $table) {
            $table->id();
            $table->string('academic_year', 9);
            $table->string('nis', 30);
            $table->string('nisn', 30)->nullable();
            $table->string('name', 150);
            $table->string('email')->nullable();
            $table->string('class_name', 70);
            $table->string('major', 120);
            $table->string('internship_status', 25)->default('pending');
            $table->foreignId('internship_period_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('student_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['academic_year', 'nis']);
            $table->index(['academic_year', 'major']);
            $table->index(['academic_year', 'internship_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('internship_rosters');
    }
};
