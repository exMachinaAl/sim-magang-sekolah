<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('timeline_events', function (Blueprint $table) {
            $table->string('scope', 20)->default('placement')->after('controller_role');
            $table->string('progress_source', 40)->default('manual')->after('scope');
            $table->unsignedSmallInteger('required_count')->default(1)->after('progress_source');
            $table->foreignId('dependency_event_id')->nullable()->after('required_count')
                ->constrained('timeline_events')->nullOnDelete();
            $table->index(['internship_period_id', 'scope']);
            $table->index(['internship_period_id', 'progress_source']);
        });

        DB::table('timeline_events')->where('controller_role', 'koordinator')->update(['scope' => 'period']);

        $automaticStages = [
            ['pattern' => '%Penempatan%', 'source' => 'placement'],
            ['pattern' => '%Jurnal%', 'source' => 'journals'],
            ['pattern' => '%Monitoring%', 'source' => 'monitoring'],
            ['pattern' => '%Penilaian%', 'source' => 'assessment'],
            ['pattern' => '%Validasi Sertifikat%', 'source' => 'certificate_approval'],
            ['pattern' => '%Pengambilan Sertifikat%', 'source' => 'certificate_distribution'],
        ];

        foreach ($automaticStages as $stage) {
            DB::table('timeline_events')->where('title', 'like', $stage['pattern'])->update([
                'scope' => 'placement',
                'progress_source' => $stage['source'],
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('timeline_events', function (Blueprint $table) {
            $table->dropIndex(['internship_period_id', 'scope']);
            $table->dropIndex(['internship_period_id', 'progress_source']);
            $table->dropConstrainedForeignId('dependency_event_id');
            $table->dropColumn(['scope', 'progress_source', 'required_count']);
        });
    }
};
