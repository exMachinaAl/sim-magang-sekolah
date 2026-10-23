<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('assessment_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('major', 120);
            $table->string('certificate_heading', 100)->default('SERTIFIKAT');
            $table->text('certificate_statement');
            $table->json('fields');
            $table->boolean('is_active')->default(true);
            $table->foreignId('based_on_id')->nullable()->constrained('assessment_templates')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
        Schema::table('assessments', function (Blueprint $table) {
            $table->foreignId('assessment_template_id')->nullable()->constrained()->restrictOnDelete();
            $table->json('template_snapshot')->nullable();
            $table->json('field_values')->nullable();
            $table->string('industry_assessor')->nullable();
            $table->date('assessed_on')->nullable();
            $table->decimal('calculated_score', 5, 2)->nullable();
            $table->text('final_score_reason')->nullable();
            $table->unsignedInteger('revision')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('assessments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('assessment_template_id');
            $table->dropColumn(['template_snapshot', 'field_values', 'industry_assessor', 'assessed_on', 'calculated_score', 'final_score_reason', 'revision']);
        });
        Schema::dropIfExists('assessment_templates');
    }
};
