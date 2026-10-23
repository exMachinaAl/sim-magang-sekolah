<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('nip', 40)->nullable()->unique()->after('name');
        });

        $demoNips = [
            'admin@simmag.test' => '198501012010011001',
            'koordinator@simmag.test' => '198602142011012002',
            'guru@simmag.test' => '198703052012012003',
            'guru2@simmag.test' => '198811202013011004',
        ];
        foreach ($demoNips as $email => $nip) {
            DB::table('users')->where('email', $email)->whereNull('nip')->update(['nip' => $nip]);
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['nip']);
            $table->dropColumn('nip');
        });
    }
};
