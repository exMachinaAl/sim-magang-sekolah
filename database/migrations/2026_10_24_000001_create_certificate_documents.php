<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('certificate_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('certificate_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('version');
            $table->json('snapshot');
            $table->longText('rendered_html');
            $table->longText('pdf_base64');
            $table->string('sha256',64);
            $table->string('source',40)->default('submission');
            $table->text('note')->nullable();
            $table->foreignId('captured_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('issued_at')->nullable();
            $table->timestamps();
            $table->unique(['certificate_id','version']);
        });
        Schema::table('certificate_movements', fn (Blueprint $table) => $table->unsignedInteger('version')->nullable());
    }

    public function down(): void
    {
        Schema::table('certificate_movements', fn (Blueprint $table) => $table->dropColumn('version'));
        Schema::dropIfExists('certificate_documents');
    }
};
