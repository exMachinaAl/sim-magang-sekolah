<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('timeline_events', function (Blueprint $table) {
            $table->string('controller_role', 20)->default('koordinator')->after('description');
            $table->foreignId('completed_by')->nullable()->after('ends_at')->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable()->after('completed_by');
        });

        Schema::create('timeline_event_completions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('timeline_event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('placement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at');
            $table->timestamps();
            $table->unique(['timeline_event_id', 'placement_id']);
        });

        DB::table('timeline_events')->where(function ($query) {
            $query->where('title', 'like', '%Jurnal%')
                ->orWhere('title', 'like', '%Pengambilan%');
        })->update(['controller_role' => 'siswa']);

        DB::table('timeline_events')->where(function ($query) {
            $query->where('title', 'like', '%Monitoring%')
                ->orWhere('title', 'like', '%Penilaian%');
        })->update(['controller_role' => 'guru']);
    }

    public function down(): void
    {
        Schema::dropIfExists('timeline_event_completions');
        Schema::table('timeline_events', function (Blueprint $table) {
            $table->dropConstrainedForeignId('completed_by');
            $table->dropColumn(['controller_role', 'completed_at']);
        });
    }
};
