<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
 public function up(): void {
  Schema::create('users', function(Blueprint $t){
   $t->id();$t->string('name');$t->string('email')->unique();$t->string('password');
   $t->string('role',30)->default('siswa')->index();$t->boolean('is_active')->default(true);
   $t->rememberToken();$t->timestamps();
  });
  Schema::create('password_reset_tokens',function(Blueprint $t){$t->string('email')->primary();$t->string('token');$t->timestamp('created_at')->nullable();});
  Schema::create('students',function(Blueprint $t){
   $t->id();$t->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();$t->string('nis')->unique();
   $t->string('nisn')->nullable();$t->string('class_name');$t->string('major');$t->timestamps();
  });
  Schema::create('companies',function(Blueprint $t){
   $t->id();$t->string('name');$t->text('address')->nullable();$t->string('contact')->nullable();$t->timestamps();
  });
  Schema::create('internship_periods',function(Blueprint $t){
   $t->id();$t->string('name');$t->date('starts_at');$t->date('ends_at');$t->boolean('is_active')->default(true);$t->timestamps();
  });
  Schema::create('placements',function(Blueprint $t){
   $t->id();$t->foreignId('student_id')->constrained()->cascadeOnDelete();$t->foreignId('company_id')->constrained()->restrictOnDelete();
   $t->foreignId('internship_period_id')->constrained()->restrictOnDelete();
   $t->foreignId('teacher_id')->constrained('users')->restrictOnDelete();
   $t->foreignId('industry_mentor_id')->constrained('users')->restrictOnDelete();
   $t->string('status',30)->default('active');$t->timestamps();$t->unique(['student_id','internship_period_id']);
  });
  Schema::create('timeline_events',function(Blueprint $t){
   $t->id();$t->foreignId('internship_period_id')->constrained()->cascadeOnDelete();
   $t->string('title');$t->text('description')->nullable();$t->date('starts_at');$t->date('ends_at');$t->timestamps();
  });
  Schema::create('journals',function(Blueprint $t){
   $t->id();$t->foreignId('placement_id')->constrained()->cascadeOnDelete();
   $t->date('activity_date');$t->text('activity');$t->text('outcome');$t->text('obstacles')->nullable();
   $t->string('status',25)->default('draft')->index();$t->text('review_note')->nullable();
   $t->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();$t->timestamp('reviewed_at')->nullable();
   $t->timestamps();$t->unique(['placement_id','activity_date']);
  });
  Schema::create('journal_attachments',function(Blueprint $t){
   $t->id();$t->foreignId('journal_id')->constrained()->cascadeOnDelete();$t->string('path');
   $t->string('original_name');$t->string('mime',80);$t->unsignedBigInteger('size');$t->timestamps();
  });
  Schema::create('monitoring_records',function(Blueprint $t){
   $t->id();$t->foreignId('placement_id')->constrained()->cascadeOnDelete();
   $t->foreignId('teacher_id')->constrained('users')->restrictOnDelete();$t->date('visit_date');
   $t->text('notes');$t->text('follow_up')->nullable();$t->timestamps();
  });
  Schema::create('assessment_criteria',function(Blueprint $t){
   $t->id();$t->string('name');$t->unsignedTinyInteger('weight');$t->unsignedTinyInteger('display_order')->default(0);$t->timestamps();
  });
  Schema::create('assessments',function(Blueprint $t){
   $t->id();$t->foreignId('placement_id')->unique()->constrained()->cascadeOnDelete();
   $t->foreignId('assessor_id')->constrained('users')->restrictOnDelete();
   $t->string('status',25)->default('draft');$t->text('notes')->nullable();$t->decimal('final_score',5,2)->nullable();
   $t->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();$t->timestamp('approved_at')->nullable();$t->timestamps();
  });
  Schema::create('assessment_scores',function(Blueprint $t){
   $t->id();$t->foreignId('assessment_id')->constrained()->cascadeOnDelete();$t->foreignId('assessment_criterion_id')->constrained()->restrictOnDelete();
   $t->unsignedTinyInteger('score');$t->timestamps();$t->unique(['assessment_id','assessment_criterion_id']);
  });
  Schema::create('certificates',function(Blueprint $t){
   $t->id();$t->foreignId('placement_id')->unique()->constrained()->cascadeOnDelete();$t->string('number')->nullable()->unique();
   $t->unsignedInteger('version')->default(1);$t->string('status',30)->default('awaiting_approval');
   $t->dateTime('pickup_at')->nullable();$t->string('pickup_location')->nullable();$t->text('pickup_requirements')->nullable();
   $t->timestamp('issued_at')->nullable();$t->timestamps();
  });
  Schema::create('certificate_approvals',function(Blueprint $t){
   $t->id();$t->foreignId('certificate_id')->constrained()->cascadeOnDelete();$t->string('party',20);
   $t->foreignId('approver_id')->nullable()->constrained('users')->nullOnDelete();$t->string('status',20)->default('pending');
   $t->text('note')->nullable();$t->timestamp('decided_at')->nullable();$t->timestamps();$t->unique(['certificate_id','party']);
  });
  Schema::create('certificate_movements',function(Blueprint $t){
   $t->id();$t->foreignId('certificate_id')->constrained()->cascadeOnDelete();$t->string('from_status',30)->nullable();
   $t->string('to_status',30);$t->foreignId('actor_id')->constrained('users')->restrictOnDelete();
   $t->string('recipient')->nullable();$t->text('note')->nullable();$t->timestamp('moved_at');$t->timestamps();
  });
  Schema::create('audit_logs',function(Blueprint $t){
   $t->id();$t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();$t->string('action',100);
   $t->string('subject_type',80);$t->unsignedBigInteger('subject_id')->nullable();$t->json('context')->nullable();
   $t->string('ip_address',45)->nullable();$t->timestamps();$t->index(['subject_type','subject_id']);
  });
 }
 public function down(): void {
  foreach(['audit_logs','certificate_movements','certificate_approvals','certificates','assessment_scores','assessments','assessment_criteria','monitoring_records','journal_attachments','journals','timeline_events','placements','internship_periods','companies','students','password_reset_tokens','users'] as $table){Schema::dropIfExists($table);}
 }
};
