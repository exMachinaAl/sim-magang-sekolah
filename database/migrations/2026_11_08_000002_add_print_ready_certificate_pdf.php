<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('certificate_documents', function (Blueprint $table): void {
            $table->longText('print_pdf_base64')->nullable()->after('sha256');
            $table->string('print_pdf_sha256', 64)->nullable()->after('print_pdf_base64');
        });
    }

    public function down(): void
    {
        if (DB::table('certificate_documents')->whereNotNull('print_pdf_base64')->exists()) {
            throw new RuntimeException('Rollback ditolak: ada PDF cetak tanpa dekorasi dalam arsip. Pertahankan versi dokumen dan pulihkan backup secara terencana.');
        }
        Schema::table('certificate_documents', function (Blueprint $table): void {
            $table->dropColumn(['print_pdf_base64', 'print_pdf_sha256']);
        });
    }
};
