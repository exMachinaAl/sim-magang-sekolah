<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration {
    public function up(): void
    {
        Schema::create('certificate_word_exports', function (Blueprint $t) {
            $t->id(); $t->foreignId('certificate_document_id')->unique()->constrained()->restrictOnDelete();
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $t->string('source_pdf_hash',64); $t->string('layout',40); $t->json('template_hashes');
            foreach (['front','back'] as $side) { $t->longText($side.'_docx'); $t->string($side.'_hash',64); }
            $t->timestamps();
        });
    }
    public function down(): void
    {
        if (DB::table('certificate_word_exports')->exists()) throw new RuntimeException('Rollback ditolak: arsip salinan Word sudah ada. Jangan hilangkan hasil cetak tersimpan.');
        Schema::dropIfExists('certificate_word_exports');
    }
};
