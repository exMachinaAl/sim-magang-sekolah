<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Pertahankan identitas pelaku lama untuk audit, tetapi hentikan akses akunnya.
        DB::table('users')->where('role', 'industri')->update([
            'role' => 'legacy_industri',
            'is_active' => false,
            'updated_at' => now(),
        ]);
        DB::table('users')->where('role', 'kepala')->update([
            'role' => 'legacy_kepala',
            'is_active' => false,
            'updated_at' => now(),
        ]);

        // Penilaian lama kini menjadi tanggung jawab guru pada penempatan yang sama.
        foreach (DB::table('placements')->select('id', 'teacher_id')->get() as $placement) {
            DB::table('assessments')->where('placement_id', $placement->id)->update([
                'assessor_id' => $placement->teacher_id,
                'updated_at' => now(),
            ]);
        }

        // Persetujuan lama tetap tersedia pada event history. Snapshot aktif disederhanakan
        // menjadi satu keputusan koordinator.
        foreach (DB::table('certificates')->select('id', 'status')->get() as $certificate) {
            DB::table('certificate_approvals')->where('certificate_id', $certificate->id)->delete();
            $alreadyIssued = ! in_array($certificate->status, ['awaiting_approval', 'revision_required'], true);
            DB::table('certificate_approvals')->insert([
                'certificate_id' => $certificate->id,
                'party' => 'coordinator',
                'status' => $alreadyIssued ? 'approved' : 'pending',
                'note' => $alreadyIssued ? 'Status persetujuan dimigrasikan dari alur lama.' : null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Disimpan nullable sebagai jejak relasi lama, tetapi tidak dipakai oleh alur baru.
        Schema::table('placements', function (Blueprint $table) {
            $table->foreignId('industry_mentor_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        DB::table('users')->where('role', 'legacy_industri')->update(['role' => 'industri']);
        DB::table('users')->where('role', 'legacy_kepala')->update(['role' => 'kepala']);

        foreach (DB::table('certificates')->select('id')->get() as $certificate) {
            DB::table('certificate_approvals')->where('certificate_id', $certificate->id)->delete();
            foreach (['industry', 'school'] as $party) {
                DB::table('certificate_approvals')->insert([
                    'certificate_id' => $certificate->id,
                    'party' => $party,
                    'status' => 'pending',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }
};
