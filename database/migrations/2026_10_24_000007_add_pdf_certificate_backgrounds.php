<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration {
    public function up(): void
    {
        Schema::table('certificate_print_templates', function (Blueprint $table) {
            $table->longText('front_pdf')->nullable();
            $table->longText('back_pdf')->nullable();
            $table->string('front_pdf_hash',64)->nullable();
            $table->string('back_pdf_hash',64)->nullable();
            $table->json('pdf_layout')->nullable();
        });
    }

    public function down(): void
    {
        if (DB::table('certificate_print_templates')->whereNotNull('front_pdf_hash')->exists()) {
            throw new RuntimeException('Rollback ditolak: template PDF telah disimpan. Pertahankan desain dan arsip.');
        }
        Schema::table('certificate_print_templates', fn(Blueprint $table)=>$table->dropColumn([
            'front_pdf','back_pdf','front_pdf_hash','back_pdf_hash','pdf_layout',
        ]));
    }
};
