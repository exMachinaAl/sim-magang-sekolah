<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration {
    public function up(): void
    {
        Schema::create('assessment_evidence',function(Blueprint $t) {
            $t->id(); $t->foreignId('assessment_id')->constrained()->restrictOnDelete();
            $t->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $t->unsignedInteger('assessment_revision'); $t->string('assessment_status',25);
            $t->string('original_name',180); $t->string('mime',80); $t->unsignedInteger('size');
            $t->string('sha256',64); $t->longText('file_base64'); $t->text('note'); $t->timestamps();
        });
    }
    public function down(): void
    {
        if (DB::table('assessment_evidence')->exists()) throw new RuntimeException('Rollback ditolak: bukti nilai industri sudah tersimpan. Pertahankan lampiran atau pulihkan backup secara terencana.');
        Schema::dropIfExists('assessment_evidence');
    }
};
