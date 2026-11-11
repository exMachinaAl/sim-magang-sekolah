<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('assessment_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('placement_id')->constrained()->restrictOnDelete();
            $table->foreignId('assessment_template_id')->constrained()->restrictOnDelete();
            $table->foreignId('submitted_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('applied_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('template_snapshot');
            $table->json('field_values');
            $table->string('industry_assessor', 150);
            $table->date('assessed_on');
            $table->decimal('calculated_score', 5, 2);
            $table->decimal('final_score', 5, 2);
            $table->string('final_score_reason', 1000)->nullable();
            $table->text('notes')->nullable();
            $table->string('status', 25)->default('submitted');
            $table->string('photo_original_name', 180)->nullable();
            $table->string('photo_mime', 80)->nullable();
            $table->unsignedInteger('photo_size')->nullable();
            $table->string('photo_sha256', 64)->nullable();
            $table->longText('photo_base64')->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->timestamps();
            $table->index(['placement_id', 'status']);
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('assessment_submissions') && DB::table('assessment_submissions')->exists()) {
            throw new RuntimeException('Rollback ditolak: setoran penilaian siswa sudah tersimpan. Pertahankan data atau pulihkan backup secara terencana.');
        }
        Schema::dropIfExists('assessment_submissions');
    }
};
