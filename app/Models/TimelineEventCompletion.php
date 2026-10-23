<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class TimelineEventCompletion extends Model {
 protected $fillable=['timeline_event_id','placement_id','completed_by','completed_at'];
 protected function casts():array{return ['completed_at'=>'datetime'];}
 public function event(){return $this->belongsTo(TimelineEvent::class,'timeline_event_id');}
 public function placement(){return $this->belongsTo(Placement::class);}
 public function completer(){return $this->belongsTo(User::class,'completed_by');}
}
