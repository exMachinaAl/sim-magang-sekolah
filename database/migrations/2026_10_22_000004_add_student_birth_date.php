<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->string('email')->nullable()->change());
        Schema::table('students', fn (Blueprint $table) => $table->date('birth_date')->nullable());
    }

    public function down(): void
    {
        if (DB::table('users')->whereNull('email')->exists()) {
            throw new RuntimeException('Akun NIS tanpa email masih digunakan; rollback tidak dapat menghapus dukungannya.');
        }
        Schema::table('students', fn (Blueprint $table) => $table->dropColumn('birth_date'));
        Schema::table('users', fn (Blueprint $table) => $table->string('email')->nullable(false)->change());
    }
};
