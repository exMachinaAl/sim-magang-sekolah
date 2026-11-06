<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessment_template_defaults', function (Blueprint $table) {
            $table->id();
            $table->string('major', 120)->unique();
            $table->foreignId('assessment_template_id')->unique()->constrained('assessment_templates')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        $now = now();
        $latest = DB::table('assessment_templates')->where('is_active', true)
            ->orderBy('major')->orderByDesc('id')->get(['id', 'major'])->unique('major');
        foreach ($latest as $template) {
            DB::table('assessment_template_defaults')->insert([
                'major' => $template->major,
                'assessment_template_id' => $template->id,
                'updated_by' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_template_defaults');
    }
};
