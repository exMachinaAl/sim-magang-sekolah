<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('journal_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('journal_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 25)->index();
            $table->text('note')->nullable();
            $table->timestamps();
        });

        // Data lama tidak mempunyai histori lengkap. Simpan kondisi terakhir sebagai
        // titik awal timeline tanpa merekayasa urutan status yang tidak diketahui.
        $journals = DB::table('journals')
            ->join('placements', 'journals.placement_id', '=', 'placements.id')
            ->join('students', 'placements.student_id', '=', 'students.id')
            ->select('journals.*', 'students.user_id as student_user_id')
            ->get();

        foreach ($journals as $journal) {
            $reviewed = in_array($journal->status, ['approved', 'revision', 'rejected'], true);
            DB::table('journal_events')->insert([
                'journal_id' => $journal->id,
                'actor_id' => $reviewed && $journal->reviewed_by ? $journal->reviewed_by : $journal->student_user_id,
                'status' => $journal->status,
                'note' => $journal->review_note ?: 'Status awal saat fitur timeline diterapkan.',
                'created_at' => $journal->reviewed_at ?: ($journal->updated_at ?: $journal->created_at),
                'updated_at' => $journal->reviewed_at ?: ($journal->updated_at ?: $journal->created_at),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_events');
    }
};
