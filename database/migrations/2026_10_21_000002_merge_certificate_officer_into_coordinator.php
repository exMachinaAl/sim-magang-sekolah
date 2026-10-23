<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // Identitas aktor pada riwayat distribusi tetap dipertahankan melalui foreign key.
        // Akunnya dihentikan agar seluruh proses sertifikat berikutnya dilakukan koordinator.
        DB::table('users')->where('role', 'petugas')->update([
            'role' => 'legacy_petugas',
            'is_active' => false,
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('users')->where('role', 'legacy_petugas')->update([
            'role' => 'petugas',
            'updated_at' => now(),
        ]);
    }
};
