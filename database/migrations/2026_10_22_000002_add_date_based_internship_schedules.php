<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('internship_schedule_batches', function (Blueprint $table) {
            $table->id();
            $table->string('academic_year', 9);
            $table->string('name', 150);
            $table->string('scope', 20);
            $table->json('selected_majors');
            $table->date('starts_at');
            $table->date('ends_at');
            $table->unsignedInteger('student_count')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['academic_year', 'starts_at']);
        });

        Schema::table('internship_rosters', function (Blueprint $table) {
            $table->date('internship_starts_at')->nullable()->after('internship_status');
            $table->date('internship_ends_at')->nullable()->after('internship_starts_at');
            $table->string('schedule_source', 30)->nullable()->after('internship_ends_at');
            $table->foreignId('schedule_batch_id')->nullable()->after('schedule_source')
                ->constrained('internship_schedule_batches')->nullOnDelete();
            $table->index(['academic_year', 'internship_starts_at', 'internship_ends_at'], 'roster_schedule_dates_index');
        });
    }

    public function down(): void
    {
        Schema::table('internship_rosters', function (Blueprint $table) {
            $table->dropForeign(['schedule_batch_id']);
            $table->dropIndex('roster_schedule_dates_index');
            $table->dropColumn([
                'internship_starts_at', 'internship_ends_at', 'schedule_source', 'schedule_batch_id',
            ]);
        });

        Schema::dropIfExists('internship_schedule_batches');
    }
};
