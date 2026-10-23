<?php
namespace App\Support;
use App\Models\AuditLog;
class Audit {
 public static function record(string $action,object $model,array $context=[]):void {
  AuditLog::create(['user_id'=>auth()->id(),'action'=>$action,'subject_type'=>class_basename($model),
   'subject_id'=>$model->id,'context'=>$context,'ip_address'=>request()->ip()]);
 }
}
