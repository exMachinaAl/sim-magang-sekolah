<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration {
    public function up(): void
    {
        Schema::table('monitoring_records', function (Blueprint $table) {
            $table->unsignedBigInteger('teacher_id')->nullable()->change();
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
        });
        // Before this upgrade only the teacher stored in teacher_id could create a note.
        DB::table('monitoring_records')->whereNull('created_by')->update(['created_by' => DB::raw('teacher_id')]);
    }

    public function down(): void
    {
        if (DB::table('monitoring_records')->whereNull('teacher_id')->orWhereColumn('created_by', '!=', 'teacher_id')->exists()) {
            throw new RuntimeException('Rollback dihentikan: identitas pencatat baru tidak dapat disimpan dalam skema lama. Jangan hilangkan catatan koordinator; pulihkan backup bila diperlukan.');
        }
        Schema::table('monitoring_records', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by');
            $table->unsignedBigInteger('teacher_id')->nullable(false)->change();
        });
    }
};
