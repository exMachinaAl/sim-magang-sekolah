<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        $assignments = [];
        $claimed = [];
        foreach (DB::table('students')->select('id', 'nis', 'user_id')->orderBy('id')->get() as $student) {
            $username = preg_replace('/\D+/', '', (string) $student->nis) ?? '';
            if ($username === '') {
                throw new RuntimeException("NIS siswa ID {$student->id} tidak mengandung angka; username belum dapat dibuat.");
            }
            if (isset($claimed[$username]) && $claimed[$username] !== $student->user_id) {
                throw new RuntimeException("Konflik normalisasi NIS: username {$username} dimiliki lebih dari satu siswa. Koreksi NIS sebelum migrasi.");
            }
            $claimed[$username] = $student->user_id;
            $assignments[$student->user_id] = $username;
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->string('username', 80)->nullable()->unique()->after('email');
        });

        foreach ($assignments as $userId => $username) {
            DB::table('users')->where('id', $userId)->update(['username' => $username]);
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique(['username']);
            $table->dropColumn('username');
        });
    }
};
