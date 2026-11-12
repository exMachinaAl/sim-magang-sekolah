<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        $duplicate = DB::table('students')->whereNotNull('nisn')->where('nisn', '!=', '')->select('nisn')
            ->groupBy('nisn')->havingRaw('COUNT(*) > 1')->value('nisn');
        if ($duplicate !== null) {
            throw new RuntimeException('NISN '.$duplicate.' dimiliki lebih dari satu siswa. Koreksi konflik sebelum migrasi.');
        }
        DB::table('students')->where('nisn', '')->update(['nisn'=>null]);

        Schema::table('students', function (Blueprint $table): void {
            $table->unique('nisn');
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table): void {
            $table->dropUnique(['nisn']);
        });
    }
};
