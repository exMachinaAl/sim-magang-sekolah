<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('certificate_print_templates', 'design_major')) {
            Schema::table('certificate_print_templates', function (Blueprint $table): void {
                $table->string('design_major', 120)->nullable()->index()->after('assessment_template_id');
            });
        }

        DB::table('certificate_print_templates')->orderBy('id')->get(['id', 'assessment_template_id'])
            ->each(function ($template): void {
                $major = DB::table('assessment_templates')->where('id', $template->assessment_template_id)->value('major');
                DB::table('certificate_print_templates')->where('id', $template->id)->whereNull('design_major')->update(['design_major' => $major]);
            });

        // MySQL DDL is not transactional. A failed first attempt may leave this empty,
        // untracked table behind; rebuilding it here is safe before the migration is recorded.
        Schema::dropIfExists('certificate_print_template_defaults');
        Schema::create('certificate_print_template_defaults', function (Blueprint $table): void {
            $table->id();
            $table->string('major', 120)->unique();
            $table->foreignId('certificate_print_template_id');
            $table->foreignId('updated_by')->nullable();
            $table->timestamps();
            $table->foreign('certificate_print_template_id', 'cert_print_default_template_fk')
                ->references('id')->on('certificate_print_templates')->restrictOnDelete();
            $table->foreign('updated_by', 'cert_print_default_editor_fk')
                ->references('id')->on('users')->nullOnDelete();
        });

        $now = now();
        $latest = DB::table('certificate_print_templates')->whereNotNull('design_major')
            ->orderBy('design_major')->orderByDesc('id')->get(['id', 'design_major'])->unique('design_major');
        foreach ($latest as $template) {
            DB::table('certificate_print_template_defaults')->insert([
                'major' => $template->design_major,
                'certificate_print_template_id' => $template->id,
                'updated_by' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('certificate_print_template_defaults');
        Schema::table('certificate_print_templates', function (Blueprint $table): void {
            $table->dropIndex(['design_major']);
            $table->dropColumn('design_major');
        });
    }
};
