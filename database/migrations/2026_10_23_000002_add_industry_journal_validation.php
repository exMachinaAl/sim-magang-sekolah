<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('journals', function (Blueprint $t) {
            $t->string('work_area', 150)->nullable();
            $t->string('starts_time', 5)->nullable();
            $t->string('ends_time', 5)->nullable();
            $t->text('equipment_notes')->nullable();
            $t->decimal('industry_score', 5, 2)->nullable();
            $t->unsignedInteger('revision')->default(1);
            $t->unsignedInteger('reviewed_revision')->nullable();
            $t->unsignedInteger('validated_revision')->nullable();
        });
        DB::table('journals')->where('status','approved')->whereNotNull('reviewed_at')->whereNotNull('reviewed_by')->update(['reviewed_revision'=>1]);
        Schema::create('industry_mentors', function (Blueprint $t) {
            $t->id(); $t->string('name', 150);
            $t->foreignId('company_id')->constrained()->restrictOnDelete();
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $t->boolean('is_active')->default(true); $t->timestamps();
        });
        Schema::create('industry_mentor_placement', function (Blueprint $t) {
            $t->foreignId('industry_mentor_id')->constrained()->cascadeOnDelete();
            $t->foreignId('placement_id')->constrained()->cascadeOnDelete();
            $t->primary(['industry_mentor_id', 'placement_id']);
        });
        Schema::create('industry_device_invites', function (Blueprint $t) {
            $t->id(); $t->foreignId('industry_mentor_id')->constrained()->restrictOnDelete();
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $t->string('token_hash', 64)->unique(); $t->timestamp('expires_at');
            $t->timestamp('used_at')->nullable(); $t->timestamps();
        });
        Schema::create('industry_devices', function (Blueprint $t) {
            $t->id(); $t->foreignId('industry_mentor_id')->constrained()->restrictOnDelete();
            $t->string('token_hash', 64)->unique(); $t->string('label', 200);
            $t->string('registered_ip', 45)->nullable(); $t->timestamp('expires_at');
            $t->timestamp('revoked_at')->nullable(); $t->timestamp('last_used_at')->nullable(); $t->timestamps();
        });
        Schema::create('journal_scan_tokens', function (Blueprint $t) {
            $t->id(); $t->foreignId('journal_id')->constrained()->cascadeOnDelete();
            $t->unsignedInteger('revision'); $t->string('token_hash', 64)->unique();
            $t->timestamp('expires_at'); $t->timestamp('used_at')->nullable(); $t->timestamps();
        });
        Schema::create('journal_industry_validations', function (Blueprint $t) {
            $t->id(); $t->foreignId('journal_id')->constrained()->restrictOnDelete();
            $t->unsignedInteger('revision'); $t->foreignId('industry_mentor_id')->constrained()->restrictOnDelete();
            $t->foreignId('industry_device_id')->constrained()->restrictOnDelete();
            $t->json('snapshot'); $t->timestamp('scanned_at'); $t->string('ip_address', 45)->nullable();
            $t->timestamps(); $t->unique(['journal_id', 'revision'], 'journal_validation_revision_unique');
        });
    }

    public function down(): void
    {
        foreach (['journal_industry_validations','journal_scan_tokens','industry_devices','industry_device_invites','industry_mentor_placement','industry_mentors'] as $table) Schema::dropIfExists($table);
        Schema::table('journals', fn (Blueprint $t) => $t->dropColumn(['work_area','starts_time','ends_time','equipment_notes','industry_score','revision','reviewed_revision','validated_revision']));
    }
};
