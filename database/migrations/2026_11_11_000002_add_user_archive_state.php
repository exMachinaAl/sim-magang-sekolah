<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->timestamp('archived_at')->nullable()->after('is_active')->index();
            $table->foreignId('archived_by')->nullable()->after('archived_at')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (DB::table('users')->whereNotNull('archived_at')->exists()) {
            throw new RuntimeException('Rollback dihentikan: masih ada akun yang diarsipkan. Menghapus kolom arsip akan membuat akun tersembunyi kembali terlihat tanpa jejak status.');
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->dropForeign(['archived_by']);
            $table->dropIndex(['archived_at']);
            $table->dropColumn(['archived_at', 'archived_by']);
        });
    }
};
