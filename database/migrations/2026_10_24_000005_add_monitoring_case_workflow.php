<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration {
    public function up(): void
    {
        Schema::table('monitoring_records', function (Blueprint $t) {
            $t->string('case_status',30)->default('unclassified')->index();
            $t->string('category',40)->nullable();
            $t->foreignId('coordinator_id')->nullable()->constrained('users')->restrictOnDelete();
            $t->unsignedInteger('revision')->default(1);
            $t->timestamp('resolved_at')->nullable();
        });
        Schema::create('monitoring_updates',function(Blueprint $t) {
            $t->id(); $t->foreignId('monitoring_record_id')->constrained()->restrictOnDelete();
            $t->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $t->json('before_state'); $t->json('after_state'); $t->text('note'); $t->timestamps();
        });
    }
    public function down(): void
    {
        if (DB::table('monitoring_updates')->exists() || DB::table('monitoring_records')->where('case_status','!=','unclassified')->exists()) {
            throw new RuntimeException('Rollback ditolak: status atau riwayat kasus sudah digunakan. Jangan hilangkan tindak lanjut.');
        }
        Schema::dropIfExists('monitoring_updates');
        Schema::table('monitoring_records',function(Blueprint $t) {
            $t->dropConstrainedForeignId('coordinator_id');
            $t->dropIndex(['case_status']); $t->dropColumn(['case_status','category','revision','resolved_at']);
        });
    }
};
