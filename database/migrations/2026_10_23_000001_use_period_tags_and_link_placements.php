<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('internship_periods', function (Blueprint $table) {
            $table->string('academic_year', 9)->nullable()->unique();
            // Retained only as legacy archive columns, never a source for PKL dates.
            $table->date('starts_at')->nullable()->change();
            $table->date('ends_at')->nullable()->change();
        });
        Schema::table('placements', function (Blueprint $table) {
            $table->foreignId('internship_roster_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('teacher_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('placements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('internship_roster_id');
        });
        Schema::table('internship_periods', function (Blueprint $table) {
            $table->dropUnique(['academic_year']);
            $table->dropColumn('academic_year');
        });
        // Nullability intentionally retained: reverting cannot invent teacher assignments or dates.
    }
};
