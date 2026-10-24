<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('certificate_print_templates', function (Blueprint $t) {
            $t->id(); $t->string('name', 150);
            $t->foreignId('assessment_template_id')->constrained()->restrictOnDelete();
            $t->string('front_variant', 40); $t->string('back_variant', 40);
            $t->json('settings'); $t->json('score_mapping')->nullable();
            $t->longText('front_docx')->nullable(); $t->longText('back_docx')->nullable();
            $t->string('front_hash', 64)->nullable(); $t->string('back_hash', 64)->nullable();
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete(); $t->timestamps();
        });
        Schema::table('certificates', function (Blueprint $t) {
            $t->foreignId('print_template_id')->nullable()->constrained('certificate_print_templates')->restrictOnDelete();
            $t->json('print_options')->nullable();
        });
        Schema::table('certificate_documents', function (Blueprint $t) {
            $t->longText('front_docx')->nullable(); $t->longText('back_docx')->nullable();
            $t->string('front_hash', 64)->nullable(); $t->string('back_hash', 64)->nullable();
        });
    }
    public function down(): void
    {
        if (\Illuminate\Support\Facades\DB::table('certificates')->whereNotNull('print_template_id')->exists()) {
            throw new RuntimeException('Rollback ditolak: ada sertifikat memakai template cetak. Pertahankan arsip dan pulihkan backup secara terencana.');
        }
        Schema::table('certificate_documents', fn (Blueprint $t) => $t->dropColumn(['front_docx','back_docx','front_hash','back_hash']));
        Schema::table('certificates', function (Blueprint $t) { $t->dropConstrainedForeignId('print_template_id'); $t->dropColumn('print_options'); });
        Schema::dropIfExists('certificate_print_templates');
    }
};
